"""
Downloads and configures portable PHP 8.3 + Composer for Windows.
Installs to %LOCALAPPDATA%\php83 — no admin required.
"""
import html.parser, io, json, os, re, shutil, subprocess, sys, urllib.request, zipfile

INSTALL_DIR = os.path.join(os.environ.get("LOCALAPPDATA", os.path.expanduser("~")), "php83")
RELEASES_URL = "https://windows.php.net/downloads/releases/"
PHP_PATTERN  = re.compile(r'php-(8\.3\.\d+)-nts-Win32-vs16-x64\.zip', re.I)
COMPOSER_URL = "https://getcomposer.org/composer-stable.phar"

EXTENSIONS = [
    "openssl", "pdo_sqlite", "pdo_mysql", "mbstring", "fileinfo",
    "curl", "zip", "xml", "bcmath", "ctype", "intl", "sodium",
]


class LinkParser(html.parser.HTMLParser):
    def __init__(self):
        super().__init__()
        self.links = []
    def handle_starttag(self, tag, attrs):
        if tag == "a":
            href = dict(attrs).get("href", "")
            if href:
                self.links.append(href)


def fetch(url, dest=None, label=""):
    print(f"  Downloading {label or url} ...", end=" ", flush=True)
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 php-bootstrap"})
    with urllib.request.urlopen(req, timeout=120) as r:
        data = r.read()
    print(f"{len(data)//1024} KB")
    if dest:
        with open(dest, "wb") as f:
            f.write(data)
    return data


def find_latest_php_zip():
    content = fetch(RELEASES_URL, label="PHP releases page").decode("utf-8", errors="ignore")
    parser = LinkParser()
    parser.feed(content)
    versions = []
    for link in parser.links:
        m = PHP_PATTERN.search(link)
        if m:
            versions.append((tuple(int(x) for x in m.group(1).split(".")), link))
    if not versions:
        raise RuntimeError("Could not find PHP 8.3 NTS VS16 x64 ZIP on windows.php.net")
    versions.sort(reverse=True)
    href = versions[0][1]
    if not href.startswith("http"):
        href = "https://windows.php.net" + href
    print(f"  Latest PHP: {versions[0][0]}")
    return href


def install_php():
    if os.path.isfile(os.path.join(INSTALL_DIR, "php.exe")):
        print(f"  PHP already at {INSTALL_DIR}")
        return

    os.makedirs(INSTALL_DIR, exist_ok=True)
    zip_url = find_latest_php_zip()
    zip_path = os.path.join(os.environ.get("TEMP", INSTALL_DIR), "php83.zip")
    fetch(zip_url, dest=zip_path, label="PHP 8.3 ZIP")

    print(f"  Extracting to {INSTALL_DIR} ...", end=" ", flush=True)
    with zipfile.ZipFile(zip_path, "r") as z:
        z.extractall(INSTALL_DIR)
    print("done")

    ini_dev = os.path.join(INSTALL_DIR, "php.ini-development")
    ini_out = os.path.join(INSTALL_DIR, "php.ini")
    shutil.copy2(ini_dev, ini_out)

    with open(ini_out, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    for ext in EXTENSIONS:
        content = re.sub(rf"^;?\s*(extension={ext})", r"\1", content, flags=re.M | re.I)

    content = re.sub(r"^;?\s*(extension_dir\s*=\s*\"ext\")", r"\1", content, flags=re.M | re.I)

    with open(ini_out, "w", encoding="utf-8") as f:
        f.write(content)

    print(f"  php.ini written with extensions: {', '.join(EXTENSIONS)}")


def install_composer():
    composer_bat = os.path.join(INSTALL_DIR, "composer.bat")
    if os.path.isfile(composer_bat):
        print("  Composer already installed.")
        return

    phar_path = os.path.join(INSTALL_DIR, "composer.phar")
    fetch(COMPOSER_URL, dest=phar_path, label="Composer PHAR")

    with open(composer_bat, "w") as f:
        f.write(f'@echo off\n"{os.path.join(INSTALL_DIR, "php.exe")}" "%~dp0composer.phar" %*\n')
    print(f"  composer.bat created at {INSTALL_DIR}")


def add_to_path():
    current = os.environ.get("PATH", "")
    if INSTALL_DIR.lower() not in current.lower():
        os.environ["PATH"] = INSTALL_DIR + os.pathsep + current
        print(f"  Added {INSTALL_DIR} to current session PATH.")

    pshell_profile = os.path.join(
        os.environ.get("USERPROFILE", "~"), "Documents", "WindowsPowerShell", "Microsoft.PowerShell_profile.ps1"
    )
    os.makedirs(os.path.dirname(pshell_profile), exist_ok=True)
    marker = f"# php83 bootstrap"
    line   = f'$env:PATH = "{INSTALL_DIR};" + $env:PATH\n'
    try:
        existing = open(pshell_profile).read() if os.path.exists(pshell_profile) else ""
    except Exception:
        existing = ""
    if INSTALL_DIR not in existing:
        with open(pshell_profile, "a") as f:
            f.write(f"\n{marker}\n{line}")
        print(f"  Added to PowerShell profile: {pshell_profile}")


def verify():
    php_exe = os.path.join(INSTALL_DIR, "php.exe")
    result = subprocess.run([php_exe, "-r", "echo PHP_VERSION;"], capture_output=True, text=True)
    if result.returncode == 0:
        print(f"  PHP {result.stdout.strip()} is working.")
    else:
        print(f"  ERROR: {result.stderr}")
        sys.exit(1)

    comp_bat = os.path.join(INSTALL_DIR, "composer.bat")
    result2 = subprocess.run([comp_bat, "--version"], capture_output=True, text=True)
    if result2.returncode == 0:
        print(f"  {result2.stdout.strip()}")
    else:
        print(f"  Composer check failed: {result2.stderr}")


if __name__ == "__main__":
    print("=== PHP + Composer bootstrap ===")
    print(f"Install dir: {INSTALL_DIR}\n")
    try:
        install_php()
        install_composer()
        add_to_path()
        print("\n=== Verification ===")
        verify()
        print(f"\nOK — run this in your terminal to use PHP right now:")
        print(f'  $env:PATH = "{INSTALL_DIR};" + $env:PATH')
    except Exception as e:
        print(f"\nFATAL: {e}")
        sys.exit(1)
