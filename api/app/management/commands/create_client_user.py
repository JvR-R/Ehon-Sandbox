# app/management/commands/create_client_user.py
# Usage: python manage.py create_client_user username@example.com --client-id=123

from django.core.management.base import BaseCommand
from django.contrib.auth.models import User
from app.models import UserScope, Client


class Command(BaseCommand):
    help = 'Create a new user linked to a client_id'

    def add_arguments(self, parser):
        parser.add_argument('username', type=str, help='Username (usually email)')
        parser.add_argument('--client-id', type=int, required=True, help='Client ID to link to')
        parser.add_argument('--password', type=str, help='Password (if not provided, will prompt)')
        parser.add_argument('--first-name', type=str, default='', help='First name')
        parser.add_argument('--last-name', type=str, default='', help='Last name')
        parser.add_argument('--role', type=str, default='CLIENT', 
                          choices=['OWNER', 'ADMIN', 'DIST', 'RESELLER', 'CLIENT'],
                          help='User role (default: CLIENT)')
        parser.add_argument('--inactive', action='store_true', help='Create as inactive user')

    def handle(self, *args, **options):
        username = options['username']
        client_id = options['client_id']
        password = options['password']
        first_name = options['first_name']
        last_name = options['last_name']
        role = options['role']
        is_active = not options['inactive']
        
        # Check if client exists
        try:
            client = Client.objects.get(client_id=client_id)
            self.stdout.write(f"✅ Found client: {client.client_name} (ID: {client_id})")
        except Client.DoesNotExist:
            self.stdout.write(self.style.ERROR(f"❌ Client ID {client_id} not found"))
            self.stdout.write("\nAvailable clients:")
            for c in Client.objects.all()[:10]:
                self.stdout.write(f"  - ID {c.client_id}: {c.client_name}")
            return
        
        # Check if user already exists
        if User.objects.filter(username=username).exists():
            self.stdout.write(self.style.ERROR(f"❌ User {username} already exists"))
            return
        
        # Get password if not provided
        if not password:
            from getpass import getpass
            password = getpass("Enter password: ")
            password_confirm = getpass("Confirm password: ")
            if password != password_confirm:
                self.stdout.write(self.style.ERROR("❌ Passwords don't match"))
                return
        
        try:
            # Create Django user
            user = User.objects.create_user(
                username=username,
                email=username if '@' in username else '',
                password=password,
                first_name=first_name,
                last_name=last_name,
                is_active=is_active
            )
            
            # Create UserScope
            user_scope = UserScope.objects.create(
                user=user,
                role=role,
                company_id=client_id
            )
            
            self.stdout.write(self.style.SUCCESS(f"\n✅ User created successfully!"))
            self.stdout.write(f"   Username: {username}")
            self.stdout.write(f"   Client: {client.client_name} (ID: {client_id})")
            self.stdout.write(f"   Role: {role}")
            self.stdout.write(f"   Active: {is_active}")
            
            # Test login
            self.stdout.write(f"\n📝 Test login with:")
            self.stdout.write(f'   curl -X POST https://your-domain.com/api/auth/login/ \\')
            self.stdout.write(f'     -H "Content-Type: application/json" \\')
            self.stdout.write(f'     -d \'{{"username":"{username}","password":"YOUR_PASSWORD"}}\'')
            
        except Exception as e:
            self.stdout.write(self.style.ERROR(f"❌ Error creating user: {str(e)}"))

