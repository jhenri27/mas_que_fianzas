import sys
sys.path.append(r"C:\Users\jhenr\AppData\Roaming\Python\Python314\site-packages")
sys.path.append(r"C:\Users\jhenr\AppData\Local\Programs\Python\Python314\Lib\site-packages")

import openpyxl

wb = openpyxl.load_workbook(r"F:\TARIFARIOS_ASEGURADORAS\TARIFARIO MULTISEGUROS\MULTISEGUROS_TARIFAS_MINIMAS_DE_SEGUROS_DE_LEY_JULIAN_TAVERAS_19.xlsx", data_only=True)
sheet = wb["100-100-200."]
for idx, r in enumerate(sheet.iter_rows(values_only=True)):
    if idx < 80:
        continue
    if idx >= 140:
        break
    cells = [str(c) if c is not None else "" for c in r]
    print(f"Row {idx+1}: {cells}")
