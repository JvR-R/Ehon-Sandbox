# app/management/commands/sync_users_from_login.py
# Usage: python manage.py sync_users_from_login

from django.core.management.base import BaseCommand
from django.contrib.auth.models import User
from django.db import connection
from app.models import UserScope


class Command(BaseCommand):
    help = 'Sync users from legacy login table to Django auth + UserScope'

    def add_arguments(self, parser):
        parser.add_argument(
            '--dry-run',
            action='store_true',
            help='Show what would be created without actually creating',
        )
        parser.add_argument(
            '--active-only',
            action='store_true',
            help='Only sync active users (active=1)',
        )

    def handle(self, *args, **options):
        dry_run = options['dry_run']
        active_only = options['active_only']
        
        # Query login table
        with connection.cursor() as cursor:
            query = "SELECT user_id, username, password, client_id, name, last_name, active, access_level FROM login"
            if active_only:
                query += " WHERE active = 1"
            cursor.execute(query)
            login_users = cursor.fetchall()
        
        self.stdout.write(f"\nFound {len(login_users)} users in login table\n")
        
        created = 0
        skipped = 0
        updated = 0
        
        for row in login_users:
            user_id, username, password, client_id, name, last_name, active, access_level = row
            
            # Check if Django user exists
            django_user = User.objects.filter(username=username).first()
            
            if django_user:
                self.stdout.write(f"  ⏭️  User exists: {username}")
                
                # Check if UserScope exists
                if not hasattr(django_user, 'userscope'):
                    if not dry_run:
                        role = self._determine_role(access_level)
                        UserScope.objects.create(
                            user=django_user,
                            role=role,
                            company_id=client_id
                        )
                        self.stdout.write(f"     ✅ Created UserScope: role={role}, client_id={client_id}")
                        updated += 1
                    else:
                        self.stdout.write(f"     [DRY RUN] Would create UserScope for client_id={client_id}")
                else:
                    self.stdout.write(f"     UserScope exists: role={django_user.userscope.role}, client_id={django_user.userscope.company_id}")
                
                skipped += 1
                continue
            
            # Create new Django user
            if dry_run:
                self.stdout.write(f"  [DRY RUN] Would create: {username} (client_id={client_id}, active={active})")
                created += 1
                continue
            
            try:
                # Create Django user
                user = User.objects.create_user(
                    username=username,
                    email=username if '@' in username else f"{username}@example.com",
                    first_name=name,
                    last_name=last_name,
                    is_active=(active == 1)
                )
                
                # Set a temporary password (they should change it)
                user.set_password('ChangeMe123!')
                user.save()
                
                # Create UserScope
                role = self._determine_role(access_level)
                UserScope.objects.create(
                    user=user,
                    role=role,
                    company_id=client_id
                )
                
                self.stdout.write(self.style.SUCCESS(
                    f"  ✅ Created: {username} (role={role}, client_id={client_id}, active={active})"
                ))
                created += 1
                
            except Exception as e:
                self.stdout.write(self.style.ERROR(f"  ❌ Error creating {username}: {str(e)}"))
        
        # Summary
        self.stdout.write("\n" + "="*70)
        if dry_run:
            self.stdout.write(self.style.WARNING("\n🔍 DRY RUN - No changes made\n"))
            self.stdout.write(f"Would create: {created} users")
        else:
            self.stdout.write(self.style.SUCCESS(f"\n✅ Sync complete!\n"))
            self.stdout.write(f"Created: {created} new users")
            self.stdout.write(f"Updated: {updated} users (added UserScope)")
            self.stdout.write(f"Skipped: {skipped} existing users")
            
            if created > 0:
                self.stdout.write(self.style.WARNING(
                    f"\n⚠️  New users have temporary password: 'ChangeMe123!'"
                ))
                self.stdout.write("   Users should change their password after first login\n")
    
    def _determine_role(self, access_level):
        """Map access_level from login table to UserScope role"""
        # Adjust these mappings based on your access_level meanings
        role_mapping = {
            1: 'OWNER',      # Highest level
            2: 'ADMIN',
            3: 'DIST',       # Distributor
            4: 'RESELLER',
            5: 'CLIENT',
            6: 'CLIENT',
            7: 'CLIENT',
            8: 'CLIENT',     # Default
        }
        return role_mapping.get(access_level, 'CLIENT')

