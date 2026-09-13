import http.server
import socketserver
import os
import sys

PORT = 8090
DIRECTORY = os.path.join(os.path.dirname(os.path.abspath(__file__)), "static-dist")

class SPAHandler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=DIRECTORY, **kwargs)

    def do_GET(self):
        try:
            # Calculate filesystem path
            path = self.translate_path(self.path)
            # If path doesn't exist and isn't an asset with extension, fallback to index.html
            if not os.path.exists(path) or os.path.isdir(path):
                if not os.path.exists(path) and "." not in os.path.basename(self.path):
                    self.path = "/index.html"
            return super().do_GET()
        except (ConnectionResetError, ConnectionAbortedError, BrokenPipeError):
            pass

    def copyfile(self, source, outputfile):
        try:
            super().copyfile(source, outputfile)
        except (ConnectionResetError, ConnectionAbortedError, BrokenPipeError):
            pass

    def log_message(self, format, *args):
        # Suppress verbose asset logs to keep console clean
        pass

class ThreadedHTTPServer(socketserver.ThreadingTCPServer, http.server.HTTPServer):
    allow_reuse_address = True
    daemon_threads = True

    def handle_error(self, request, client_address):
        # Ignore connection aborted errors quietly
        exc_type, exc_val, _ = sys.exc_info()
        if exc_type in (ConnectionResetError, ConnectionAbortedError, BrokenPipeError):
            return
        super().handle_error(request, client_address)

if __name__ == "__main__":
    os.chdir(DIRECTORY)
    with ThreadedHTTPServer(("", PORT), SPAHandler) as httpd:
        print(f"Serving robust threaded SPA on port {PORT}...", flush=True)
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            pass
