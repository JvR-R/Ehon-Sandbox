# app/management/commands/link_user_to_client.py
# Usage: python manage.py link_user_to_client admin@boral --client-id=123

from django.core.management.base import BaseCommand
from django.contrib.auth.models import User
from django.db import connection
from app.models import UserScope, Client


class Command(BaseCommand):
    help = 'Link an existing Django user to a client_id via UserScope'

    def add_arguments(self, parser):
        parser.add_argument('username', type=str, help='Username to link')
        parser.add_argument('--client-id', type=int, help='Client ID to link to')
        parser.add_argument('--client-name', type=str, help='Client name (searches clients table)')
        parser.add_argument('--from-login', action='store_true', 
                          help='Auto-find client_id from login table using username')
        parser.add_argument('--role', type=str, default='CLIENT',
                          choices=['OWNER', 'ADMIN', 'DIST', 'RESELLER', 'CLIENT'],
                          help='User role (default: CLIENT)')

    def handle(self, *args, **options):
        username = options['username']
        client_id = options['client_id']
        client_name = options['client_name']
        from_login = options['from_login']
        role = options['role']
        
        # Get Django user
        try:
            user = User.objects.get(username=username)
            self.stdout.write(f"✅ Found Django user: {username}")
        except User.DoesNotExist:
            self.stdout.write(self.style.ERROR(f"❌ User '{username}' not found in Django auth"))
            self.stdout.write("\nAvailable users:")
            for u in User.objects.all()[:10]:
                self.stdout.write(f"  - {u.username}")
            return
        
        # Check if UserScope already exists
        if hasattr(user, 'userscope'):
            scope = user.userscope
            self.stdout.write(self.style.WARNING(
                f"⚠️  User already has UserScope: role={scope.role}, client_id={scope.company_id}"
            ))
            response = input("Update it? (y/N): ")
            if response.lower() != 'y':
                return
            # Will update below
            updating = True
        else:
            updating = False
        
        # Determine client_id
        if from_login:
            # Look up in login table
            with connection.cursor() as cursor:
                cursor.execute(
                    "SELECT client_id, name, last_name, access_level FROM login WHERE username = %s",
                    [username]
                )
                row = cursor.fetchone()
                
                if row:
                    client_id = row[0]
                    access_level = row[3]
                    self.stdout.write(f"✅ Found in login table:")
                    self.stdout.write(f"   - client_id: {client_id}")
                    self.stdout.write(f"   - name: {row[1]} {row[2]}")
                    self.stdout.write(f"   - access_level: {access_level}")
                    
                    # Auto-determine role from access_level
                    role = self._map_access_level_to_role(access_level)
                    self.stdout.write(f"   - mapped role: {role}")
                else:
                    self.stdout.write(self.style.ERROR(
                        f"❌ Username '{username}' not found in login table"
                    ))
                    return
        
        elif client_name:
            # Search by client name
            clients = Client.objects.filter(client_name__icontains=client_name)
            if clients.count() == 0:
                self.stdout.write(self.style.ERROR(f"❌ No client found matching '{client_name}'"))
                return
            elif clients.count() > 1:
                self.stdout.write(self.style.WARNING(f"⚠️  Multiple clients found:"))
                for c in clients:
                    self.stdout.write(f"   - ID {c.client_id}: {c.client_name}")
                self.stdout.write("\nUse --client-id instead")
                return
            else:
                client = clients.first()
                client_id = client.client_id
                self.stdout.write(f"✅ Found client: {client.client_name} (ID: {client_id})")
        
        elif not client_id:
            self.stdout.write(self.style.ERROR(
                "❌ Must provide --client-id, --client-name, or --from-login"
            ))
            return
        
        # Verify client exists
        try:
            client = Client.objects.get(client_id=client_id)
            self.stdout.write(f"✅ Client verified: {client.client_name} (ID: {client_id})")
        except Client.DoesNotExist:
            self.stdout.write(self.style.ERROR(f"❌ Client ID {client_id} not found"))
            self.stdout.write("\nAvailable clients:")
            for c in Client.objects.all()[:10]:
                self.stdout.write(f"   - ID {c.client_id}: {c.client_name}")
            return
        
        # Create or update UserScope
        try:
            if updating:
                user.userscope.role = role
                user.userscope.company_id = client_id
                user.userscope.save()
                action = "updated"
            else:
                UserScope.objects.create(
                    user=user,
                    role=role,
                    company_id=client_id
                )
                action = "created"
            
            self.stdout.write(self.style.SUCCESS(f"\n✅ UserScope {action}!"))
            self.stdout.write(f"   User: {username}")
            self.stdout.write(f"   Client: {client.client_name} (ID: {client_id})")
            self.stdout.write(f"   Role: {role}")
            
            # Show what they can access
            from app.models import ConsoleAssociation
            console_count = ConsoleAssociation.objects.filter(client_id=client_id).count()
            self.stdout.write(f"\n📊 This user can access:")
            self.stdout.write(f"   - {console_count} consoles")
            
            # Test login command
            self.stdout.write(f"\n📝 Test login:")
            self.stdout.write(f'   curl -X POST https://your-domain.com/api/auth/login/ \\')
            self.stdout.write(f'     -H "Content-Type: application/json" \\')
            self.stdout.write(f'     -d \'{{"username":"{username}","password":"PASSWORD"}}\'')
            
        except Exception as e:
            self.stdout.write(self.style.ERROR(f"❌ Error: {str(e)}"))
    
    def _map_access_level_to_role(self, access_level):
        """Map access_level from login table to role"""
        mapping = {
            1: 'OWNER',
            2: 'ADMIN',
            3: 'DIST',
            4: 'RESELLER',
            5: 'CLIENT',
            6: 'CLIENT',
            7: 'CLIENT',
            8: 'CLIENT',
        }
        return mapping.get(access_level, 'CLIENT')

