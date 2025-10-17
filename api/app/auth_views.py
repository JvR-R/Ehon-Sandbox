# app/auth_views.py - Authentication endpoints

from rest_framework.views import APIView
from rest_framework.response import Response
from rest_framework import status
from rest_framework.permissions import AllowAny
from django.contrib.auth import authenticate
from oauth2_provider.models import Application, AccessToken, RefreshToken
from django.utils import timezone
from datetime import timedelta
import secrets
import logging

logger = logging.getLogger(__name__)


class LoginView(APIView):
    """
    Simple login endpoint for username/password authentication.
    
    **Endpoint:** POST /api/auth/login/
    **Authentication:** Not required
    
    **Request Body:**
    ```json
    {
        "username": "your_username",
        "password": "your_password"
    }
    ```
    
    **Response (Success):**
    ```json
    {
        "access_token": "abc123...",
        "token_type": "Bearer",
        "expires_in": 36000,
        "refresh_token": "def456...",
        "scope": "read write"
    }
    ```
    
    **Usage:**
    ```bash
    curl -X POST https://your-domain.com/api/auth/login/ \
      -H "Content-Type: application/json" \
      -d '{"username":"user","password":"pass"}'
    ```
    
    Then use the token in subsequent requests:
    ```bash
    curl https://your-domain.com/api/vmi/ \
      -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
    ```
    """
    permission_classes = [AllowAny]

    def post(self, request):
        username = request.data.get('username')
        password = request.data.get('password')

        if not username or not password:
            logger.warning("Login attempt with missing credentials")
            return Response(
                {
                    'error': 'invalid_request',
                    'error_description': 'Username and password are required'
                },
                status=status.HTTP_400_BAD_REQUEST
            )

        # Authenticate user
        user = authenticate(username=username, password=password)
        
        if user is None:
            # Check if user exists but password is wrong
            from django.contrib.auth import get_user_model
            User = get_user_model()
            try:
                existing_user = User.objects.get(username=username)
                logger.warning(f"Failed login - user exists but wrong password: {username} (active: {existing_user.is_active}, has_usable_password: {existing_user.has_usable_password()})")
            except User.DoesNotExist:
                logger.warning(f"Failed login - user does not exist: {username}")
            
            return Response(
                {
                    'error': 'invalid_grant',
                    'error_description': 'Invalid username or password'
                },
                status=status.HTTP_401_UNAUTHORIZED
            )

        if not user.is_active:
            logger.warning(f"Login attempt for inactive user: {username}")
            return Response(
                {
                    'error': 'invalid_grant',
                    'error_description': 'User account is disabled'
                },
                status=status.HTTP_401_UNAUTHORIZED
            )

        try:
            # Get or create a default application for password grant
            application, created = Application.objects.get_or_create(
                name='Default Password Grant Application',
                defaults={
                    'client_type': Application.CLIENT_CONFIDENTIAL,
                    'authorization_grant_type': Application.GRANT_PASSWORD,
                    'skip_authorization': True,
                }
            )

            # Generate tokens
            access_token_str = secrets.token_hex(30)
            refresh_token_str = secrets.token_hex(30)
            
            expires_in = 36000  # 10 hours
            expires = timezone.now() + timedelta(seconds=expires_in)
            
            # Create access token
            access_token = AccessToken.objects.create(
                user=user,
                token=access_token_str,
                application=application,
                expires=expires,
                scope='read write'
            )
            
            # Create refresh token
            refresh_token = RefreshToken.objects.create(
                user=user,
                token=refresh_token_str,
                application=application,
                access_token=access_token
            )

            logger.info(f"Successful login for user: {username}")

            return Response({
                'access_token': access_token_str,
                'token_type': 'Bearer',
                'expires_in': expires_in,
                'refresh_token': refresh_token_str,
                'scope': 'read write'
            }, status=status.HTTP_200_OK)

        except Exception as e:
            logger.error(f"Error generating token for user {username}: {str(e)}")
            return Response(
                {
                    'error': 'server_error',
                    'error_description': 'Failed to generate authentication token'
                },
                status=status.HTTP_500_INTERNAL_SERVER_ERROR
            )


class RefreshTokenView(APIView):
    """
    Refresh an access token using a refresh token.
    
    **Endpoint:** POST /api/auth/refresh/
    **Authentication:** Not required
    
    **Request Body:**
    ```json
    {
        "refresh_token": "your_refresh_token"
    }
    ```
    
    **Response:**
    ```json
    {
        "access_token": "new_abc123...",
        "token_type": "Bearer",
        "expires_in": 36000,
        "refresh_token": "new_def456...",
        "scope": "read write"
    }
    ```
    """
    permission_classes = [AllowAny]

    def post(self, request):
        refresh_token_str = request.data.get('refresh_token')

        if not refresh_token_str:
            return Response(
                {
                    'error': 'invalid_request',
                    'error_description': 'Refresh token is required'
                },
                status=status.HTTP_400_BAD_REQUEST
            )

        try:
            refresh_token = RefreshToken.objects.select_related('user', 'application').get(
                token=refresh_token_str
            )
            
            # Check if refresh token is revoked
            if refresh_token.revoked:
                logger.warning(f"Attempt to use revoked refresh token")
                return Response(
                    {
                        'error': 'invalid_grant',
                        'error_description': 'Refresh token has been revoked'
                    },
                    status=status.HTTP_401_UNAUTHORIZED
                )

            user = refresh_token.user
            application = refresh_token.application

            # Revoke old access token
            if refresh_token.access_token:
                refresh_token.access_token.delete()

            # Generate new tokens
            access_token_str = secrets.token_hex(30)
            new_refresh_token_str = secrets.token_hex(30)
            
            expires_in = 36000  # 10 hours
            expires = timezone.now() + timedelta(seconds=expires_in)
            
            # Create new access token
            access_token = AccessToken.objects.create(
                user=user,
                token=access_token_str,
                application=application,
                expires=expires,
                scope='read write'
            )
            
            # Revoke old refresh token and create new one
            refresh_token.revoked = timezone.now()
            refresh_token.save()
            
            new_refresh_token = RefreshToken.objects.create(
                user=user,
                token=new_refresh_token_str,
                application=application,
                access_token=access_token
            )

            logger.info(f"Token refreshed for user: {user.username}")

            return Response({
                'access_token': access_token_str,
                'token_type': 'Bearer',
                'expires_in': expires_in,
                'refresh_token': new_refresh_token_str,
                'scope': 'read write'
            }, status=status.HTTP_200_OK)

        except RefreshToken.DoesNotExist:
            logger.warning("Invalid refresh token used")
            return Response(
                {
                    'error': 'invalid_grant',
                    'error_description': 'Invalid refresh token'
                },
                status=status.HTTP_401_UNAUTHORIZED
            )
        except Exception as e:
            logger.error(f"Error refreshing token: {str(e)}")
            return Response(
                {
                    'error': 'server_error',
                    'error_description': 'Failed to refresh token'
                },
                status=status.HTTP_500_INTERNAL_SERVER_ERROR
            )


class LogoutView(APIView):
    """
    Logout endpoint - revokes the current access token.
    
    **Endpoint:** POST /api/auth/logout/
    **Authentication:** Required
    
    **Response:**
    ```json
    {
        "message": "Successfully logged out"
    }
    ```
    """
    def post(self, request):
        # Get the token from the Authorization header
        auth_header = request.META.get('HTTP_AUTHORIZATION', '')
        
        if auth_header.startswith('Bearer '):
            token_str = auth_header[7:]
            
            try:
                # Find and delete the access token
                access_token = AccessToken.objects.get(token=token_str)
                
                # Also revoke associated refresh tokens
                RefreshToken.objects.filter(access_token=access_token).update(
                    revoked=timezone.now()
                )
                
                access_token.delete()
                
                logger.info(f"User logged out: {request.user.username}")
                
                return Response({
                    'message': 'Successfully logged out'
                }, status=status.HTTP_200_OK)
                
            except AccessToken.DoesNotExist:
                pass
        
        return Response({
            'message': 'Logged out'
        }, status=status.HTTP_200_OK)

