# app/management/commands/check_user.py
# Usage: python manage.py check_user username@example.com

from django.core.management.base import BaseCommand
from django.contrib.auth import get_user_model

User = get_user_model()


class Command(BaseCommand):
    help = 'Check if a user exists and their status'

    def add_arguments(self, parser):
        parser.add_argument('username', type=str, help='Username to check')

    def handle(self, *args, **options):
        username = options['username']
        
        try:
            user = User.objects.get(username=username)
            
            self.stdout.write(self.style.SUCCESS(f'\n✅ User found: {username}'))
            self.stdout.write(f'   - ID: {user.id}')
            self.stdout.write(f'   - Email: {user.email}')
            self.stdout.write(f'   - Active: {user.is_active}')
            self.stdout.write(f'   - Staff: {user.is_staff}')
            self.stdout.write(f'   - Superuser: {user.is_superuser}')
            self.stdout.write(f'   - Last login: {user.last_login}')
            self.stdout.write(f'   - Date joined: {user.date_joined}')
            
            if hasattr(user, 'userscope'):
                scope = user.userscope
                self.stdout.write(f'\n   UserScope:')
                self.stdout.write(f'   - Role: {scope.role}')
                self.stdout.write(f'   - Company ID: {scope.company_id}')
            else:
                self.stdout.write(self.style.WARNING('\n   ⚠️  No UserScope found'))
            
            if not user.is_active:
                self.stdout.write(self.style.ERROR('\n   ❌ User is INACTIVE'))
                self.stdout.write('   To activate: python manage.py activate_user ' + username)
            else:
                self.stdout.write(self.style.SUCCESS('\n   ✅ User is active'))
            
            # Check if user has usable password
            if not user.has_usable_password():
                self.stdout.write(self.style.ERROR('\n   ❌ User has no usable password'))
                self.stdout.write('   To set password: python manage.py changepassword ' + username)
            
        except User.DoesNotExist:
            self.stdout.write(self.style.ERROR(f'\n❌ User not found: {username}'))
            self.stdout.write('\n   Available users:')
            for u in User.objects.all()[:10]:
                self.stdout.write(f'   - {u.username} (active: {u.is_active})')

