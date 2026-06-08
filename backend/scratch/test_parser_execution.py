import sys
sys.path.append(r"C:\Users\jhenr\AppData\Roaming\Python\Python314\site-packages")
sys.path.append(r"C:\Users\jhenr\AppData\Local\Programs\Python\Python314\Lib\site-packages")
sys.path.append(r"c:\wamp64\www\PLATAFORMA_INTEGRADA\backend\scripts")

import json
from etl_parser import parse_file

mapping = {
    "mode": "blocks",
    "capacidad": {"type": "column", "value": ""},
    "tarifa_base": {"type": "column", "value": "PRECIO INTENSION PARA VENTA"}
}

res = parse_file(
    r"F:\TARIFARIOS_ASEGURADORAS\TARIFARIO MULTISEGUROS\MULTISEGUROS_TARIFAS_MINIMAS_DE_SEGUROS_DE_LEY_JULIAN_TAVERAS_19.xlsx",
    json.dumps(mapping),
    "100-100-200."
)

print("Exito:", res.get("exito"))
if res.get("exito"):
    print("Total valid records:", res.get("total_validos"))
    print("First 5 records:")
    for r in res.get("datos", [])[:5]:
        print("  -", r)
    print("Last 5 records:")
    for r in res.get("datos", [])[-5:]:
        print("  -", r)
    print("Errors count:", len(res.get("errores", [])))
else:
    print("Error msg:", res.get("mensaje"))
