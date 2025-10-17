# app/management/commands/activate_user.py
# Usage: python manage.py activate_user username@example.com

from django.core.management.base import BaseCommand
from django.contrib.auth import get_user_model

User = get_user_model()


class Command(BaseCommand):
    help = 'Activate a user account'

    def add_arguments(self, parser):
        parser.add_argument('username', type=str, help='Username to activate')

    def handle(self, *args, **options):
        username = options['username']
        
        try:
            user = User.objects.get(username=username)
            
            if user.is_active:
                self.stdout.write(self.style.WARNING(f'User {username} is already active'))
            else:
                user.is_active = True
                user.save()
                self.stdout.write(self.style.SUCCESS(f'✅ User {username} has been activated'))
            
        except User.DoesNotExist:
            self.stdout.write(self.style.ERROR(f'❌ User not found: {username}'))

