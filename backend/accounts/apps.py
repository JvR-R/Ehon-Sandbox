from django.apps import AppConfig

class AccountsConfig(AppConfig):
    name  = "accounts"   # ← path to the code on disk
    label = "app"        # ← old label, so ContentTypes & tables stay the same
    verbose_name = "Accounts / Auth"
