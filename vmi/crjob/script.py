import os
import mysql.connector
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from tabulate import tabulate
import argparse

# MySQL database connection details
db_config = {
    'host': 'localhost',
    'user': 'ehontech_admin',
    'password': '$_i_dev789mysql',
    'database': 'ehontech_vmi'
}

email_config = {
    'sender_email': 'vmi@ehon.com.au',
    'sender_password': 'VMIEHON2023',
    'smtp_server': 'mail.ehon.com.au',
    'smtp_port': 465
}

def connect_to_database():
    try:
        connection = mysql.connector.connect(**db_config)
        return connection
    except mysql.connector.Error as err:
        print(f"Error: {err}")
        return None

def execute_query(connection, query, params=None):
    cursor = connection.cursor()
    cursor.execute(query, params)
    rows = cursor.fetchall()
    cursor.close()
    return rows

def send_email(receiver_email, email_subject, email_content):
    message = MIMEMultipart()
    message['From'] = email_config['sender_email']
    message['To'] = receiver_email
    message['Subject'] = email_subject

    # Create an HTML table
    html_table = f'<html><body><table style="margin: 0 auto; text-align: center; border-style: solid; justify-content: center;">{email_content}</table></body></html>'
    message.attach(MIMEText(html_table, 'html'))

    print(f"Sender email: {email_config['sender_email']}")
    print(f"Recipient email: {receiver_email}")

    try:
        # Use SMTP_SSL for a secure connection
        server = smtplib.SMTP_SSL(email_config['smtp_server'], email_config['smtp_port'])
        server.login(email_config['sender_email'], email_config['sender_password'])
        server.send_message(message)
        server.quit()
        print("Email sent successfully.")
    except Exception as e:
        print(f"Failed to send email. Error: {e}")

# Uncomment and complete the send_emails function as needed
# def send_emails(company_id):
#     connection = connect_to_database()
#     if not connection:
#         return

#     sql_query = """
#     SELECT log.email, log.notification_type, clc.company_name, cs.site_name, cs.site_no, cs.tank_no, cs.product_name, cs.current_volume, cs.ullage, cs.current_percent
#     FROM ehontech_vmi.company_users AS log
#     INNER JOIN ehontech_vmi.client_sites AS cs ON log.maincompany_id = cs.company_id
#     INNER JOIN ehontech_vmi.clients_login_companies AS clc ON (clc.company_id, clc.sub_id) = (cs.company_id, cs.sub_id)
#     WHERE cs.company_id = %s
#     """

#     rows = execute_query(connection, sql_query, (company_id,))
#     # Continue with your logic here...

#     connection.close()

if __name__ == "__main__":
    # Retrieve the company ID argument
    parser = argparse.ArgumentParser()
    parser.add_argument('--company-id', type=int, help='Company ID')
    args = parser.parse_args()
    company_id = args.company_id

    # Uncomment the following line if using send_emails function
    # send_emails(company_id)

    # Example test call
    send_email("apdonovan@bigpond.com", "test", "test2")
