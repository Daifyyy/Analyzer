import os
import json
import platform
import subprocess


# Dynamické zjištění cesty ke skriptu
script_dir = os.path.dirname(os.path.abspath(__file__))
config_file = os.path.join(script_dir, "config.json")

with open(config_file, "r") as f:
    config = json.load(f)


# Zjištění názvu aktuálního počítače
pc_name = platform.node()

# Výběr správné konfigurace
if pc_name in config:
    git_path = config[pc_name]["git_path"]
    repo_path = config[pc_name]["repo_path"]
else:
    raise ValueError(f"PC '{pc_name}' nebyl nalezen v konfiguraci")

# Přidání Git binárky do PATH
os.environ["PATH"] = git_path + os.pathsep + os.environ["PATH"]

# Přechod do pracovního adresáře
if os.path.exists(repo_path):
    os.chdir(repo_path)
    print(f"Pracovní adresář nastaven na: {os.getcwd()}")
else:
    raise FileNotFoundError(f"Repozitář nebyl nalezen na cestě: {repo_path}")

# Spuštění příkazu git pull
try:
    result = subprocess.run(
        ["git", "pull", "origin", "main"],
        capture_output=True,
        text=True,
        check=True
    )
    print("Výstup Git příkazu:")
    print(result.stdout)
except subprocess.CalledProcessError as e:
    print("Chyba při volání Git příkazu:")
    print(e.stderr)
    exit()

# Zkontrolovat změněné soubory
def check_changes():
    result = subprocess.run(
        ["git", "diff", "--name-only", "HEAD@{1}", "HEAD"],
        capture_output=True,
        text=True,
        check=True
    )
    return result.stdout.strip().split("\n")

changed_files = check_changes()
if changed_files:
    print("Změněné soubory po aktualizaci:")
    for file in changed_files:
        print(f" - {file}")
else:
    print("Žádné změny po aktualizaci.")
