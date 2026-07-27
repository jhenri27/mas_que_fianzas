#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
🖥️ SERVICIO BRIDGE DESKTOP PARA BOT-VISUAL-TEST-E2E (SESSION 1)
Escucha peticiones HTTP en localhost:9998 y lanza navegadores Chrome VISIBLES en la pantalla del usuario.
"""

import sys
import os
import json
import urllib.parse
from http.server import HTTPServer, BaseHTTPRequestHandler

# Importar Runner Visual
sys.path.append(os.path.dirname(__file__))
from bot_visual_runner import BotVisualRunner

PORT = 9998

class DesktopRunnerHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        # Desactivar logs por defecto de HTTP server en consola para no saturar
        pass

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        self.end_headers()

    def do_GET(self):
        self.handle_request()

    def do_POST(self):
        self.handle_request()

    def handle_request(self):
        parsed_path = urllib.parse.urlparse(self.path)
        if parsed_path.path in ('/run', '/run_test'):
            params = urllib.parse.parse_qs(parsed_path.query)
            
            # Leer body si es POST JSON
            content_length = int(self.headers.get('Content-Length', 0))
            if content_length > 0:
                body_bytes = self.rfile.read(content_length)
                try:
                    body_json = json.loads(body_bytes.decode('utf-8'))
                    for k, v in body_json.items():
                        params[k] = [str(v)]
                except Exception:
                    pass

            perfil = params.get('perfil', ['5'])[0]
            modulo = params.get('modulo', ['polizas'])[0]
            escenario = params.get('escenario', ['emision_individual'])[0]
            visible_str = params.get('visible', ['true'])[0]
            visible = (str(visible_str).lower() in ('true', '1', 'yes'))

            print(f"🖥️ [Desktop Service Session 1] Licitación de prueba visual en pantalla: Perfil={perfil}, Módulo={modulo}, Escenario={escenario}")
            
            try:
                runner = BotVisualRunner(perfil=perfil, modulo=modulo, escenario=escenario, visible=visible)
                reporte = runner.run()
                response_data = {"exito": True, "reporte": reporte}
            except Exception as e:
                response_data = {"exito": False, "mensaje": str(e)}

            self.send_response(200)
            self.send_header('Content-Type', 'application/json; charset=utf-8')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            self.wfile.write(json.dumps(response_data, ensure_ascii=False).encode('utf-8'))
        elif parsed_path.path == '/status':
            self.send_response(200)
            self.send_header('Content-Type', 'application/json; charset=utf-8')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            self.wfile.write(json.dumps({"status": "online", "session": "desktop_session_1"}).encode('utf-8'))
        else:
            self.send_response(404)
            self.end_headers()

def run_server():
    server_address = ('127.0.0.1', PORT)
    httpd = HTTPServer(server_address, DesktopRunnerHandler)
    print(f"=======================================================================")
    print(f"🚀 SERVICIO DESKTOP VISIBLE BOT-VISUAL-TEST-E2E ACTIVO EN PUERTO {PORT}")
    print(f"=======================================================================")
    print(f"Cualquier prueba lanzada desde la Web o API abrirá Chrome visible en su pantalla.")
    httpd.serve_forever()

if __name__ == "__main__":
    run_server()
