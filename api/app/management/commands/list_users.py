# app/management/commands/list_users.py
# Usage: python manage.py list_users

from django.core.management.base import BaseCommand
from django.contrib.auth import get_user_model

User = get_user_model()


class Command(BaseCommand):
    help = 'List all users and their status'

    def handle(self, *args, **options):
        users = User.objects.all().order_by('username')
        
        self.stdout.write(self.style.SUCCESS(f'\nTotal users: {users.count()}\n'))
        
        self.stdout.write(f'{"Username":<40} {"Active":<10} {"Staff":<10} {"Super":<10}')
        self.stdout.write('-' * 70)
        
        for user in users:
            active = '✅' if user.is_active else '❌'
            staff = '✅' if user.is_staff else '  '
            superuser = '✅' if user.is_superuser else '  '
            
            self.stdout.write(f'{user.username:<40} {active:<10} {staff:<10} {superuser:<10}')

