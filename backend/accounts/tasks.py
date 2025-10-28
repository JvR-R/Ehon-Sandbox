"""
Celery tasks for async operations
"""
import logging
import imaplib
import time
from decimal import Decimal
from celery import shared_task
from django.core.mail import EmailMessage
from django.utils import timezone
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

LOG = logging.getLogger("accounts.tasks")


@shared_task(bind=True, max_retries=3)
def send_level_alert_email(self, site_name: str, tank_id: int, 
                          current_volume: str, current_percent: str,
                          ullage: str, receiver_email: str, cc_emails: str = None):
    """
    Send low level alert email asynchronously
    
    Args:
        site_name: Name of the site
        tank_id: Tank identifier
        current_volume: Current volume in liters (as string for Decimal precision)
        current_percent: Current percentage (as string)
        ullage: Ullage in liters (as string)
        receiver_email: Primary recipient email
        cc_emails: Optional comma-separated CC emails
    """
    try:
        # Format numbers for display
        volume = Decimal(current_volume)
        percent = Decimal(current_percent)
        ull = Decimal(ullage)
        
        formatted_volume = f"{volume:,.0f}"
        formatted_percent = f"{percent:.2f}"
        formatted_ullage = f"{ull:,.0f}"
        
        # Prepare email content
        subject = f"Ehon Alert for {site_name}, Tank {tank_id} is Low"
        
        html_content = f"""
        <html>
        <body style="font-family: Arial, sans-serif;">
            <h2 style="color: #d32f2f;">⚠️ Low Level Alert</h2>
            <p><strong>Site:</strong> {site_name}</p>
            <p><strong>Tank:</strong> {tank_id}</p>
            <hr style="border: 1px solid #e0e0e0;">
            <table style="border-collapse: collapse; margin: 10px 0;">
                <tr>
                    <td style="padding: 8px;"><strong>Current Percent:</strong></td>
                    <td style="padding: 8px;">{formatted_percent}%</td>
                </tr>
                <tr>
                    <td style="padding: 8px;"><strong>Volume:</strong></td>
                    <td style="padding: 8px;">{formatted_volume} L</td>
                </tr>
                <tr>
                    <td style="padding: 8px;"><strong>Ullage:</strong></td>
                    <td style="padding: 8px;">{formatted_ullage} L</td>
                </tr>
            </table>
            <hr style="border: 1px solid #e0e0e0;">
            <p style="color: #666; font-size: 12px;">
                This is an automated alert from Ehon VMI System.<br>
                Alert sent at: {timezone.localtime().strftime('%Y-%m-%d %H:%M:%S AEST')}
            </p>
        </body>
        </html>
        """
        
        # Plain text fallback
        text_content = f"""
Ehon Alert for Site: {site_name}
Tank: {tank_id}
Current Percent: {formatted_percent}%
Volume: {formatted_volume} L
Ullage: {formatted_ullage} L

This is an automated alert from Ehon VMI System.
Alert sent at: {timezone.localtime().strftime('%Y-%m-%d %H:%M:%S AEST')}
        """
        
        # Create email message
        email = EmailMessage(
            subject=subject,
            body=text_content,
            from_email='vmi@ehon.com.au',
            to=[receiver_email],
        )
        
        # Add HTML alternative
        email.content_subtype = "html"
        email.body = html_content
        
        # Add CC recipients if provided
        if cc_emails:
            cc_list = [email.strip() for email in cc_emails.split(',') if email.strip()]
            email.cc = cc_list
        
        # Send email via SMTP
        email.send(fail_silently=False)
        
        LOG.info("✅ Level alert email sent to %s for site %s, tank %s", 
                receiver_email, site_name, tank_id)
        
        # Save to IMAP Sent folder
        save_to_sent_folder(
            subject=subject,
            html_content=html_content,
            text_content=text_content,
            to_email=receiver_email,
            from_email='vmi@ehon.com.au',
            cc_emails=cc_emails
        )
        
        # Save to email historic database
        save_email_historic(receiver_email)
        
        return f"Email sent successfully to {receiver_email}"
        
    except Exception as exc:
        LOG.error("Failed to send level alert email to %s: %s", 
                 receiver_email, exc, exc_info=True)
        # Retry with exponential backoff
        raise self.retry(exc=exc, countdown=60 * (2 ** self.request.retries))


def save_to_sent_folder(subject: str, html_content: str, text_content: str, 
                        to_email: str, from_email: str, cc_emails: str = None):
    """
    Save sent email to IMAP Sent folder (matches PHP save_mail function)
    """
    try:
        # Create the email message
        msg = MIMEMultipart('alternative')
        msg['Subject'] = subject
        msg['From'] = from_email
        msg['To'] = to_email
        msg['Date'] = timezone.localtime().strftime('%a, %d %b %Y %H:%M:%S %z')
        
        if cc_emails:
            msg['Cc'] = cc_emails
        
        # Add text and HTML parts
        part1 = MIMEText(text_content, 'plain', 'utf-8')
        part2 = MIMEText(html_content, 'html', 'utf-8')
        msg.attach(part1)
        msg.attach(part2)
        
        # Connect to IMAP server and save to Sent folder
        imap = imaplib.IMAP4_SSL('mail.ehon.com.au', 993)
        imap.login('vmi@ehon.com.au', 'VMIEHON2023')
        
        # Append to Sent folder
        message_bytes = msg.as_bytes()
        result = imap.append(
            'INBOX.Sent',
            '',
            imaplib.Time2Internaldate(time.time()),
            message_bytes
        )
        
        if result[0] == 'OK':
            LOG.info("📁 Email saved to Sent folder")
        else:
            LOG.error("Failed to save to Sent folder: %s", result)
        
        imap.logout()
        
    except Exception as exc:
        # Log but don't fail - email was already sent successfully
        LOG.warning("Could not save to IMAP Sent folder (non-critical): %s", exc, exc_info=True)


@shared_task
def save_email_historic(receiver_email: str):
    """
    Save email sending record to database
    Note: This is optional - email still works if this fails
    """
    from django.db import connection
    
    try:
        # Use raw SQL to insert - matches actual table structure
        with connection.cursor() as cursor:
            cursor.execute(
                """
                INSERT INTO email_historic (email_date, email_time, receiver_email)
                VALUES (%s, %s, %s)
                """,
                [timezone.now().date(), timezone.now().time(), receiver_email]
            )
        LOG.info("📧 Email historic saved for %s", receiver_email)
    except Exception as exc:
        # Log but don't fail - email was already sent successfully
        LOG.warning("Could not save email historic (non-critical): %s", exc)

