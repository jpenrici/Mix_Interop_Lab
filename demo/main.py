# demo/main.py

import sys
import subprocess
import time
from PySide6.QtCore import QUrl
from PySide6.QtWidgets import QApplication, QMainWindow
from PySide6.QtWebEngineWidgets import QWebEngineView


class FrankensteinWindow(QMainWindow):
    def __init__(self, php_process):
        super().__init__()
        self.php_process = php_process
        self.setWindowTitle("Project Mix - Interop Lab Environment")
        self.resize(800, 600)

        self.browser = QWebEngineView()
        self.browser.setUrl(QUrl("http://localhost:8080/index.php"))
        self.setCentralWidget(self.browser)

    def closeEvent(self, event):
        self.php_process.terminate()
        event.accept()


def start_php_server():
    process = subprocess.Popen(["php", "-S", "localhost:8080", "-t", "public"])
    time.sleep(1)
    return process


if __name__ == "__main__":
    app = QApplication(sys.argv)

    php_proc = start_php_server()

    window = FrankensteinWindow(php_proc)
    window.show()

    exit_code = app.exec()
    php_proc.terminate()
    sys.exit(exit_code)
