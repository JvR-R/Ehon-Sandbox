# app/management/commands/import_user_from_login.py
# Usage: python manage.py import_user_from_login username@example.com

from django.core.management.base import BaseCommand
from django.contrib.auth.models import User
from django.db import connection
from app.models import UserScope, Client


class Command(BaseCommand):
    help = 'Import a single user from the login table to Django auth + UserScope'

    def add_arguments(self, parser):
        parser.add_argument('username', type=str, help='Username from login table')
        parser.add_argument(
            '--new-password',
            type=str,
            help='Set a new password (if not provided, uses temporary password)'
        )
        parser.add_argument(
            '--keep-password',
            action='store_true',
            help='Try to import password from login table (only if plain text)'
        )
        parser.add_argument(
            '--update',
            action='store_true',
            help='Update existing user if already exists'
        )

    def handle(self, *args, **options):
        username = options['username']
        new_password = options['new_password']
        keep_password = options['keep_password']
        update = options['update']
        
        # Fetch user from login table
        with connection.cursor() as cursor:
            cursor.execute("""
                SELECT user_id, username, password, access_level, client_id, 
                       name, last_name, active, last_date, last_time
                FROM login 
                WHERE username = %s
            """, [username])
            row = cursor.fetchone()
        
        if not row:
            self.stdout.write(self.style.ERROR(f"❌ User '{username}' not found in login table"))
            self.stdout.write("\nSearching for similar usernames:")
            with connection.cursor() as cursor:
                cursor.execute("SELECT username FROM login WHERE username LIKE %s LIMIT 10", [f"%{username}%"])
                similar = cursor.fetchall()
                for s in similar:
                    self.stdout.write(f"   - {s[0]}")
            return
        
        # Parse login table data
        (user_id, db_username, db_password, access_level, client_id, 
         first_name, last_name, active, last_date, last_time) = row
        
        self.stdout.write(f"\n✅ Found user in login table:")
        self.stdout.write(f"   User ID: {user_id}")
        self.stdout.write(f"   Username: {db_username}")
        self.stdout.write(f"   Name: {first_name} {last_name}")
        self.stdout.write(f"   Client ID: {client_id}")
        self.stdout.write(f"   Access Level: {access_level}")
        self.stdout.write(f"   Active: {active}")
        self.stdout.write(f"   Last login: {last_date} {last_time}")
        
        # Verify client exists
        try:
            client = Client.objects.get(client_id=client_id)
            self.stdout.write(f"   Client: {client.client_name}")
        except Client.DoesNotExist:
            self.stdout.write(self.style.ERROR(f"   ❌ Client ID {client_id} not found!"))
            return
        
        # Map access_level to role
        role = self._map_access_level(access_level)
        self.stdout.write(f"   Role: {role}")
        
        # Determine password to use
        if new_password:
            password_to_use = new_password
            password_source = "provided new password"
        elif keep_password and len(db_password) < 50:
            # Assume it's plain text if short (Django hashes are longer)
            password_to_use = db_password
            password_source = "imported from login table"
            self.stdout.write(self.style.WARNING(
                "   ⚠️  Attempting to use password from login table (may not work if hashed differently)"
            ))
        else:
            password_to_use = "ChangeMe123!"
            password_source = "temporary password 'ChangeMe123!'"
        
        # Check if Django user exists
        django_user = User.objects.filter(username=db_username).first()
        
        if django_user:
            if not update:
                self.stdout.write(self.style.ERROR(
                    f"\n❌ Django user '{db_username}' already exists. Use --update to update it."
                ))
                return
            
            # Update existing user
            self.stdout.write(f"\n🔄 Updating existing Django user...")
            django_user.email = db_username if '@' in db_username else ''
            django_user.first_name = first_name or ''
            django_user.last_name = last_name or ''
            django_user.is_active = (active == 1)
            if new_password or keep_password:
                django_user.set_password(password_to_use)
            django_user.save()
            action = "updated"
        else:
            # Create new Django user
            self.stdout.write(f"\n➕ Creating new Django user...")
            django_user = User.objects.create_user(
                username=db_username,
                email=db_username if '@' in db_username else '',
                password=password_to_use,
                first_name=first_name or '',
                last_name=last_name or '',
                is_active=(active == 1)
            )
            action = "created"
        
        # Create or update UserScope
        if hasattr(django_user, 'userscope'):
            self.stdout.write(f"🔄 Updating UserScope...")
            django_user.userscope.role = role
            django_user.userscope.company_id = client_id
            django_user.userscope.save()
            scope_action = "updated"
        else:
            self.stdout.write(f"➕ Creating UserScope...")
            UserScope.objects.create(
                user=django_user,
                role=role,
                company_id=client_id
            )
            scope_action = "created"
        
        # Success summary
        self.stdout.write(self.style.SUCCESS(f"\n✅ Import complete!"))
        self.stdout.write(f"   Django user: {action}")
        self.stdout.write(f"   UserScope: {scope_action}")
        self.stdout.write(f"   Username: {db_username}")
        self.stdout.write(f"   Password: {password_source}")
        self.stdout.write(f"   Client: {client.client_name} (ID: {client_id})")
        self.stdout.write(f"   Role: {role}")
        self.stdout.write(f"   Active: {django_user.is_active}")
        
        # Show what they can access
        from app.models import ConsoleAssociation
        console_count = ConsoleAssociation.objects.filter(client_id=client_id).count()
        self.stdout.write(f"\n📊 This user can access:")
        self.stdout.write(f"   - {console_count} consoles for {client.client_name}")
        
        # Test login command
        self.stdout.write(f"\n📝 Test login:")
        self.stdout.write(f'   curl -X POST https://your-domain.com/api/auth/login/ \\')
        self.stdout.write(f'     -H "Content-Type: application/json" \\')
        if new_password:
            self.stdout.write(f'     -d \'{{"username":"{db_username}","password":"YOUR_NEW_PASSWORD"}}\'')
        elif keep_password:
            self.stdout.write(f'     -d \'{{"username":"{db_username}","password":"PASSWORD_FROM_LOGIN_TABLE"}}\'')
        else:
            self.stdout.write(f'     -d \'{{"username":"{db_username}","password":"ChangeMe123!"}}\'')
            self.stdout.write(self.style.WARNING(
                f"\n   ⚠️  User should change password after first login!"
            ))
    
    def _map_access_level(self, access_level):
        """Map access_level from login table to UserScope role"""
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

