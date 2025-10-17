# inside app/migrations/0002_add_tx_indexes.py
from django.db import migrations

class Migration(migrations.Migration):
    dependencies = [("app", "0001_initial")]

    operations = [
        migrations.RunSQL(
            sql="""
                CREATE INDEX idx_tx_date      ON client_transaction (transaction_date);
                CREATE INDEX idx_tx_tank      ON client_transaction (tank_id);
                CREATE INDEX idx_tx_card      ON client_transaction (card_number(20));
                CREATE INDEX uid_date_idx     ON client_transaction (uid, transaction_date);
                CREATE INDEX date_time_idx    ON client_transaction (transaction_date DESC, transaction_time DESC);
            """,
            reverse_sql="""
                DROP INDEX idx_tx_date      ON client_transaction;
                DROP INDEX idx_tx_tank      ON client_transaction;
                DROP INDEX idx_tx_card      ON client_transaction;
                DROP INDEX uid_date_idx     ON client_transaction;
                DROP INDEX date_time_idx    ON client_transaction;
            """,
        ),
    ]
