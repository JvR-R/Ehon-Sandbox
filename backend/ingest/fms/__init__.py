from importlib import import_module
from pathlib import Path

HANDLERS = {}

for path in Path(__file__).parent.glob("*.py"):
    if path.stem in {"__init__", "__pycache__"}:
        continue
    mod = import_module(f".{path.stem}", package=__name__)
    HANDLERS.update(mod.TOPICS)

