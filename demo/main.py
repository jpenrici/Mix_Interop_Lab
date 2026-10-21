#!/usr/bin/env python3
# demo/main.py
"""Starts the PHP built-in server and shows its UI in a PySide6 web view."""

from __future__ import annotations

import logging
import os
import shutil
import socket
import subprocess
import sys
import time
from pathlib import Path

from PySide6.QtCore import QUrl
from PySide6.QtWebEngineWidgets import QWebEngineView
from PySide6.QtWidgets import QApplication, QMainWindow

BASE_DIR = Path(__file__).resolve().parent
PUBLIC_DIR = BASE_DIR / "public"
HOST = os.environ.get("DEMO_HOST", "127.0.0.1")
PORT = int(os.environ.get("DEMO_PORT", "8080"))
START_TIMEOUT_S = 10.0
STOP_TIMEOUT_S = 5.0

log = logging.getLogger("demo")


def start_php_server() -> subprocess.Popen[bytes]:
    """Launches `php -S` and blocks until it accepts connections."""
    php = shutil.which("php")
    if php is None:
        raise RuntimeError("PHP executable not found in PATH.")

    # The built-in server runs on the `cli-server` SAPI, where FFI needs ffi.enable=1.
    command = [php, "-d", "ffi.enable=1", "-S", f"{HOST}:{PORT}", "-t", str(PUBLIC_DIR)]
    log.info("Starting PHP server: %s", " ".join(command))
    process = subprocess.Popen(command, cwd=BASE_DIR)

    try:
        _wait_until_ready(process)
    except Exception:
        stop_php_server(process)
        raise
    return process


def _wait_until_ready(process: subprocess.Popen[bytes]) -> None:
    deadline = time.monotonic() + START_TIMEOUT_S
    while time.monotonic() < deadline:
        if (code := process.poll()) is not None:
            raise RuntimeError(
                f"PHP server exited early with code {code} (is port {PORT} in use?)."
            )
        try:
            with socket.create_connection((HOST, PORT), timeout=0.25):
                return
        except OSError:
            time.sleep(0.1)
    raise TimeoutError(f"PHP server did not start within {START_TIMEOUT_S:.0f}s.")


def stop_php_server(process: subprocess.Popen[bytes]) -> None:
    """Terminates the server; safe to call more than once."""
    if process.poll() is not None:
        return
    process.terminate()
    try:
        process.wait(timeout=STOP_TIMEOUT_S)
    except subprocess.TimeoutExpired:
        log.warning("PHP server did not stop in time; killing it.")
        process.kill()
        process.wait()


class MainWindow(QMainWindow):
    def __init__(self, url: str) -> None:
        super().__init__()
        self.setWindowTitle("Project Mix - Interop Lab Environment")
        self.resize(1000, 700)

        self.browser = QWebEngineView()
        self.browser.setUrl(QUrl(url))
        self.setCentralWidget(self.browser)


def main() -> int:
    logging.basicConfig(
        level=logging.INFO, format="%(levelname)s %(name)s: %(message)s"
    )
    app = QApplication(sys.argv)

    try:
        php_process = start_php_server()
    except (RuntimeError, TimeoutError, OSError) as exc:
        log.error("%s", exc)
        return 1

    app.aboutToQuit.connect(lambda: stop_php_server(php_process))

    window = MainWindow(f"http://{HOST}:{PORT}/ui/index.html")
    window.show()
    return app.exec()


if __name__ == "__main__":
    sys.exit(main())
