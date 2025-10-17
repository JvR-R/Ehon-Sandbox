from django.http import JsonResponse, HttpResponse
from django.views.decorators.csrf import csrf_exempt
import logging
import os
import redis
import json
from .mqtt_utils import is_valid_uid, publish_mqtt_message
from .tasks import manager_task
from .models import ClientTag, Console, Driver, Vehicle, Tank, AlarmConfig, Product, Pumps


logger = logging.getLogger('mqtt_client')

redis_host = 'localhost'
redis_port = 6379
redis_db = 0
redis_password = os.getenv('REDIS_PASSWORD', None)

redis_client = redis.StrictRedis(
    host=redis_host,
    port=redis_port,
    db=redis_db,
    password=redis_password,
    decode_responses=True
)

def home(request):
    return HttpResponse("Hello, this is the mqtt_cs Django app!")

def ensure_manager_running(uid):
    logger.info(f"ensure_manager_running called for UID {uid}")
    manager_key = f"uid:{uid}:manager_running"
    val = redis_client.get(manager_key)
    logger.info(f"manager_running={val} for UID {uid} before updating")
    if val != "1":
        # Do NOT set manager_running=1 here.
        logger.info(f"manager_running is not '1', so we'll queue the manager task for UID {uid}")
        manager_task.delay(uid)



@csrf_exempt
def update_estop(request):
    logger.info("update_estop endpoint called")
    if request.method == 'POST':
        uid = request.POST.get('uid')
        estop = request.POST.get('estop')
        if uid and estop and is_valid_uid(uid):
            try:
                estop_int = int(estop)
            except ValueError:
                return JsonResponse({'error': 'Invalid E-Stop value.'}, status=400)
            # E-Stop message: lpush for high priority
            message = {"estop": estop_int}
            # redis_client.lpush(f"uid:{uid}:messages", json.dumps(message))
            topic=f"fms/{uid}"
            publish_mqtt_message(topic, message)
            return JsonResponse({'status': 'success', 'message': 'E-Stop command Sent.'})
        else:
            return JsonResponse({'error': 'UID and E-Stop required.'}, status=400)
    return JsonResponse({'error': 'Invalid request method.'}, status=405)

@csrf_exempt
def system_state(request):
    logger.info("system_state endpoint called")
    if request.method == 'POST':
        uid = request.POST.get('uid')
        system_state = request.POST.get('system_state')
        if uid and system_state and is_valid_uid(uid):
            try:
                system_state_int = int(system_state)
            except ValueError:
                return JsonResponse({'error': 'Invalid system_state value.'}, status=400)
            # E-Stop message: lpush for high priority
            message = {"system_state": system_state_int}
            # redis_client.lpush(f"uid:{uid}:messages", json.dumps(message))
            topic=f"fms/{uid}"
            publish_mqtt_message(topic, message)
            return JsonResponse({'status': 'success', 'message': 'system_state command Sent.'})
        else:
            return JsonResponse({'error': 'UID and system_state required.'}, status=400)
    return JsonResponse({'error': 'Invalid request method.'}, status=405)

@csrf_exempt
def update_pulse_rate(request):
    if request.method == 'POST':
        uid = request.POST.get('uid')
        tank = request.POST.get('tank')
        pulse_rate = request.POST.get('pulse_rate')

        if uid and is_valid_uid(uid):
            try:
                tank_int = int(tank)
                pulse_rate_float = float(pulse_rate)
            except:
                return JsonResponse({'status': 'error', 'message': 'Invalid parameters.'}, status=400)

            # Normal priority: rpush
            message = {"PULSE": {"tank_num": tank_int, "pulse_rate": pulse_rate_float}}
            redis_client.rpush(f"uid:{uid}:messages", json.dumps(message))
            ensure_manager_running(uid)
            return JsonResponse({'status': 'success', 'message': 'Pulse rate update queued.'})
        else:
            return JsonResponse({'status': 'error', 'message': 'Invalid or missing UID.'}, status=400)
    else:
        return JsonResponse({'status': 'error', 'message': 'Invalid request method.'}, status=405)
    
@csrf_exempt
def update_tanks_view(request):
    if request.method == 'POST':
        uid = request.POST.get('uid')

        if uid and is_valid_uid(uid):
            try:
                console = Console.objects.get(uid=uid)
                crc_tank_db = str(console.crc_tank)

                tanks = Tank.objects.filter(uid=uid).select_related('product')
                if tanks.exists():
                    tanks_data = "\n".join([
                        ",".join([
                            str(tank.tank_id),
                            str(tank.capacity),
                            tank.product.product_name  # <-- Access product name through related product
                        ]) for tank in tanks
                    ]) + "\n"
                else:
                    return JsonResponse({'status': 'error', 'message': 'Tanks model not found.'}, status=400)
            # except:
            #     return JsonResponse({'status': 'error', 'message': 'Invalid parameters.'}, status=400)

            except Console.DoesNotExist:
                return JsonResponse({'status': 'error', 'message': 'Console not found.'}, status=400)
            except Exception as e:
                # Log the error for debugging
                print(e)
                return JsonResponse({'status': 'error', 'message': 'An unexpected error occurred.'}, status=400)

            message = {
                "is_update": "false",
                "CRC": crc_tank_db,
                "tanks": tanks_data
            }
            redis_client.rpush(f"uid:{uid}:messages", json.dumps(message))
            ensure_manager_running(uid)
            return JsonResponse({'status': 'success', 'message': 'Tanks update queued.'})
        else:
            return JsonResponse({'status': 'error', 'message': 'Invalid or missing UID.'}, status=400)
    else:
        return JsonResponse({'status': 'error', 'message': 'Invalid request method.'}, status=405)

@csrf_exempt
def update_pumps_view(request):
    if request.method == 'POST':
        uid = request.POST.get('uid')
        inuse = 0
        nozzle_switch= 0
        keypad_start = 0
        if uid and is_valid_uid(uid):
            try:
                console = Console.objects.get(uid=uid)
                crc_pump_db = str(console.crc_pumps)

                pumps = Pumps.objects.filter(uid=uid)
                if pumps.exists():
                    pumps_data = "\n".join([
                        ",".join([
                            str(pump.Nozzle_number),
                            str(pump.Pulse_rate),
                            str(pump.tank_id),
                            str(inuse),
                            str(nozzle_switch),
                            str(keypad_start)
                        ]) for pump in pumps
                    ]) + "\n"
                else:
                    return JsonResponse({'status': 'error', 'message': 'Pumps model not found.'}, status=400)
            # except:
            #     return JsonResponse({'status': 'error', 'message': 'Invalid parameters.'}, status=400)

            except Console.DoesNotExist:
                return JsonResponse({'status': 'error', 'message': 'Console not found.'}, status=400)
            except Exception as e:
                # Log the error for debugging
                print(e)
                return JsonResponse({'status': 'error', 'message': 'An unexpected error occurred.'}, status=400)

            message = {
                "is_update": "false",
                "CRC": crc_pump_db,
                "pumps": pumps_data
            }
            redis_client.rpush(f"uid:{uid}:messages", json.dumps(message))
            ensure_manager_running(uid)
            return JsonResponse({'status': 'success', 'message': 'Pumps update queued.'})
        else:
            return JsonResponse({'status': 'error', 'message': 'Invalid or missing UID.'}, status=400)
    else:
        return JsonResponse({'status': 'error', 'message': 'Invalid request method.'}, status=405)


@csrf_exempt
def update_tg_view(request):
    if request.method == 'POST':
        uid = request.POST.get('uid')

        if uid and is_valid_uid(uid):
            try:
                console = Console.objects.get(uid=uid)
                crc_tank_db = str(console.crc_tank)

                # Get tanks with related product and alarm config
                tanks = Tank.objects.filter(uid=uid).select_related('product')
                if tanks.exists():
                    tanks_data = []
                    for tank in tanks:
                        # Get alarm config for this tank
                        alarm_config = AlarmConfig.objects.get(uid=uid, tank_id=tank.tank_id)
                        
                        tanks_data.append(",".join([
                            str(tank.tank_gauge_id),
                            str(tank.tank_id),
                            "1",
                            str(tank.product.product_density),  # Access via product relation
                            str(tank.chart_id if tank.chart_id is not None else 0),
                            str(tank.offset_tank if tank.offset_tank is not None else 0),
                            str(alarm_config.crithigh_alarm if alarm_config.crithigh_alarm is not None else 0),
                            str(alarm_config.high_alarm if alarm_config.high_alarm is not None else 0),
                            str(alarm_config.low_alarm if alarm_config.low_alarm is not None else 0),
                            str(alarm_config.critlow_alarm if alarm_config.critlow_alarm is not None else 0),
                            f"{tank.current_volume:.2f}" if tank.current_volume is not None else "0",
                             " ".join(filter(None, [
                                tank.dipr_date.strftime('%Y-%m-%d') if tank.dipr_date else '',
                                tank.dipr_time.strftime('%H:%M:%S') if tank.dipr_time else ''
                            ])),
                            tank.recon_time.strftime('%H:%M:%S') if tank.recon_time else ''
                        ]))
                    tanks_data = "\n".join(tanks_data) + "\n"
                else:
                    return JsonResponse({'status': 'error', 'message': 'No tank status data found.'}, status=400)

            except Console.DoesNotExist:
                return JsonResponse({'status': 'error', 'message': 'Console not found.'}, status=400)
            except Exception as e:
                print(f"Error in update_tg_view: {str(e)}")
                return JsonResponse({'status': 'error', 'message': 'An unexpected error occurred.'}, status=400)

            message = {
                "is_update": "false",
                "CRC": crc_tank_db,
                "tank_gauges": tanks_data
            }
            redis_client.rpush(f"uid:{uid}:messages", json.dumps(message))
            ensure_manager_running(uid)
            return JsonResponse({'status': 'success', 'message': 'TG data update queued.'})
        else:
            return JsonResponse({'status': 'error', 'message': 'Invalid or missing UID.'}, status=400)
    else:
        return JsonResponse({'status': 'error', 'message': 'Invalid request method.'}, status=405)

    
@csrf_exempt
def update_tags_view(request):
    if request.method == 'POST':
        uid = request.POST.get('uid')
        client_id = request.POST.get('client_id')
        if uid and is_valid_uid(uid) and client_id:
            try:
                console = Console.objects.get(uid=uid)
                crc_auth_db = str(console.crc_auth)
                
                tags = ClientTag.objects.filter(client_id=client_id)
                if tags.exists():
                    authenticators_data = "\n".join([
                        ",".join([
                            str(tag.id or 0),
                            str(tag.card_number or '0'),
                            str(tag.card_type or '0'),
                            str(int(tag.list_driver) if tag.list_driver is not None else 0),
                            str(int(tag.list_vehicle) if tag.list_vehicle is not None else 0),
                            str(0 if tag.driver_prompt is None or int(tag.driver_prompt) == 999 else int(tag.driver_prompt)),
                            str(0 if tag.prompt_vehicle is None or int(tag.prompt_vehicle) == 999 else int(tag.prompt_vehicle)),
                            str(int(tag.projectnum_prompt) if tag.projectnum_prompt else 0),
                            str(int(tag.odo_prompt) if tag.odo_prompt else 0),
                            str(tag.pin_number or '0'),
                            str(int(tag.enabled_prompt) if tag.enabled_prompt else 0)
                        ]) for tag in tags
                    ]) + "\n"

                    # Store all needed data in message
                    message = {
                        "is_update": "false",
                        "CRC": crc_auth_db,
                        "authenticators": authenticators_data
                    }
                    redis_client.rpush(f"uid:{uid}:messages", json.dumps(message))
                    ensure_manager_running(uid)
                    logger.info(f"Tags update enqueued for UID {uid}.")
                    return JsonResponse({'status': 'success', 'message': 'Tags update enqueued.'})
                else:
                    logger.error(f"No authenticators data found for UID {uid}.")
                    return JsonResponse({'status': 'error', 'message': 'No authenticators data found.'}, status=404)
            except Console.DoesNotExist:
                logger.error(f"No Console found for UID {uid}")
                return JsonResponse({'status': 'error', 'message': 'Console not found.'}, status=404)
            except Exception as e:
                logger.exception(f"Unexpected error in update_tags_view: {e}")
                return JsonResponse({'status': 'error', 'message': 'Unable to process request.'}, status=500)
        else:
            return JsonResponse({'status': 'error', 'message': 'Invalid or missing UID/client_id.'}, status=400)
    else:
        return JsonResponse({'status': 'error', 'message': 'Invalid request method.'}, status=405)

@csrf_exempt
def update_drivers_view(request):
    if request.method == 'POST':
        uid = request.POST.get('uid')
        client_id = request.POST.get('client_id')
        if uid and client_id and is_valid_uid(uid):
            try:
                console = Console.objects.get(uid=uid)
                crc_driver_db = str(console.crc_driver)

                drivers = Driver.objects.filter(client_id=client_id)
                if drivers.exists():
                    drivers_data = "\n".join([
                        ",".join([
                            str(int(driver.driver_id)),
                            str(driver.first_name + (" " + driver.surname if driver.surname else ""))
                        ]) for driver in drivers
                    ]) + "\n"

                    message = {"is_update": "false", "CRC": crc_driver_db, "drivers": drivers_data}
                    redis_client.rpush(f"uid:{uid}:messages", json.dumps(message))
                    ensure_manager_running(uid)
                    logger.info(f"Drivers update enqueued for UID {uid}.")
                    return JsonResponse({'status': 'success', 'message': 'Drivers update enqueued.'})
                else:
                    logger.error(f"No drivers data found for UID {uid}.")
                    return JsonResponse({'status': 'error', 'message': 'No drivers data found.'}, status=404)
            except Console.DoesNotExist:
                return JsonResponse({'status': 'error', 'message': 'Console not found.'}, status=404)
        else:
            return JsonResponse({'status': 'error', 'message': 'Missing UID/client_id.'}, status=400)
    else:
        return JsonResponse({'status': 'error', 'message': 'Invalid request method.'}, status=405)

@csrf_exempt
def update_vehicles_view(request):
    if request.method == 'POST':
        uid = request.POST.get('uid')
        client_id = request.POST.get('client_id')
        if uid and client_id and is_valid_uid(uid):
            try:
                console = Console.objects.get(uid=uid)
                crc_vehicle_db = str(console.crc_vehicle)

                vehicles = Vehicle.objects.filter(client_id=client_id)
                if vehicles.exists():
                    vehicles_data = "\n".join([
                        ",".join([
                            str(int(vehicle.vehicle_id)),
                            str(vehicle.vehicle_name or ''),
                            str(vehicle.vehicle_rego or '')
                        ]) for vehicle in vehicles
                    ]) + "\n"

                    message = {"is_update": "false", "CRC": crc_vehicle_db, "vehicles": vehicles_data}
                    redis_client.rpush(f"uid:{uid}:messages", json.dumps(message))
                    ensure_manager_running(uid)
                    logger.info(f"Vehicles update enqueued for UID {uid}.")
                    return JsonResponse({'status': 'success', 'message': 'Vehicles update enqueued.'})
                else:
                    logger.error(f"No vehicles data found for UID {uid}.")
                    return JsonResponse({'status': 'error', 'message': 'No vehicles data found.'}, status=404)
            except Console.DoesNotExist:
                return JsonResponse({'status': 'error', 'message': 'Console not found.'}, status=404)
        else:
            return JsonResponse({'status': 'error', 'message': 'Missing UID/client_id.'}, status=400)
    else:
        return JsonResponse({'status': 'error', 'message': 'Invalid request method.'}, status=405)

@csrf_exempt
def start_transaction(request):
    if request.method == 'POST':
        uid = request.POST.get('uid', '').strip()
        site_id = request.POST.get('site_id', '').strip()
        pin = request.POST.get('pin', '').strip()
        pump_number = request.POST.get('pump_number', '').strip()

        if not uid or not is_valid_uid(uid):
            return JsonResponse({'status': 'error', 'message': 'Invalid or missing UID.'}, status=400)

        message = {"type": "transaction", "site_id": site_id, "pin": pin, "pump_number": pump_number}
        redis_client.rpush(f"uid:{uid}:messages", json.dumps(message))
        ensure_manager_running(uid)
        logger.info(f"Transaction queued for UID {uid}.")
        return JsonResponse({'status': 'success', 'message': 'Transaction queued.'})
    else:
        return JsonResponse({'status': 'error', 'message': 'Invalid request method.'}, status=405)
