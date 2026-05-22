import zipfile
import xml.etree.ElementTree as ET
import re
import sys
import json
import csv
import datetime

# Clean commission text and convert to float
def clean_commission(val):
    if not val:
        return 0.0
    val_str = str(val).replace('%', '').strip()
    try:
        return float(val_str)
    except ValueError:
        return 0.0

# Split full name into first and last names
def split_name(full_name):
    if not full_name:
        return "", ""
    # Strip parenthesized text (like "(Ama Auto Import)")
    clean = re.sub(r'\(.*?\)', '', full_name).strip()
    parts = clean.split()
    if len(parts) == 0:
        return "", ""
    elif len(parts) == 1:
        return parts[0], ""
    elif len(parts) == 2:
        return parts[0], parts[1]
    elif len(parts) == 3:
        return parts[0], parts[1] + " " + parts[2]
    else:
        # 2 first names, and the rest are last names
        return parts[0] + " " + parts[1], " ".join(parts[2:])

# Parse Spanish dates and other formats
MONTHS_ES = {
    'ene': 1, 'enero': 1,
    'feb': 2, 'febrero': 2,
    'mar': 3, 'marzo': 3,
    'abr': 4, 'abril': 4,
    'may': 5, 'mayo': 5,
    'jun': 6, 'junio': 6,
    'jul': 7, 'julio': 7,
    'ago': 8, 'agosto': 8,
    'sep': 9, 'sept': 9, 'septiembre': 9,
    'oct': 10, 'octubre': 10,
    'nov': 11, 'noviembre': 11,
    'dic': 12, 'diciembre': 12
}

def parse_spanish_date(val):
    if not val:
        return None
    val_str = str(val).strip().lower()
    
    # Try ISO date format: YYYY-MM-DD
    if re.match(r'^\d{4}-\d{2}-\d{2}$', val_str):
        return val_str
        
    # Try DD/MM/YYYY
    match = re.match(r'^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$', val_str)
    if match:
        day, month, year = int(match.group(1)), int(match.group(2)), int(match.group(3))
        return f"{year:04d}-{month:02d}-{day:02d}"
        
    # Try YYYY/MM/DD
    match = re.match(r'^(\d{4})[/-](\d{1,2})[/-](\d{1,2})$', val_str)
    if match:
        year, month, day = int(match.group(1)), int(match.group(2)), int(match.group(3))
        return f"{year:04d}-{month:02d}-{day:02d}"
        
    # Try "mmm DD, YYYY" (e.g. "sept 24, 1994" or "feb 26, 1900")
    match = re.match(r'^([a-z]+)\s*(\d{1,2})\s*,\s*(\d{4})$', val_str)
    if match:
        month_name = match.group(1)
        day = int(match.group(2))
        year = int(match.group(3))
        month = MONTHS_ES.get(month_name, 1)
        return f"{year:04d}-{month:02d}-{day:02d}"
        
    # Try "DD de [month_name] de YYYY"
    match = re.match(r'^(\d{1,2})\s+de\s+([a-z]+)\s+de\s+(\d{4})$', val_str)
    if match:
        day = int(match.group(1))
        month_name = match.group(2)
        year = int(match.group(3))
        month = MONTHS_ES.get(month_name, 1)
        return f"{year:04d}-{month:02d}-{day:02d}"
        
    # Excel date offset (numeric string)
    try:
        if val_str.replace('.', '', 1).isdigit():
            val_num = float(val_str)
            base_date = datetime.date(1899, 12, 30)
            target_date = base_date + datetime.timedelta(days=val_num)
            return target_date.strftime('%Y-%m-%d')
    except:
        pass
        
    return None

# Normalize Bank and Account Type
def normalize_bank_and_account_type(banco_val, tipo_cuenta_val):
    banco_val = str(banco_val or '').strip()
    tipo_cuenta_val = str(tipo_cuenta_val or '').strip()
    
    banco_normalized = banco_val
    tipo_cuenta_normalized = 'Ahorro'
    
    # Strict redirection for 'Reservas' in account type field
    tipo_cuenta_lower = tipo_cuenta_val.lower()
    if 'reserva' in tipo_cuenta_lower:
        banco_normalized = 'Banreservas'
        tipo_cuenta_normalized = 'Ahorro'
    else:
        if 'ahorro' in tipo_cuenta_lower:
            tipo_cuenta_normalized = 'Ahorro'
        elif 'corriente' in tipo_cuenta_lower:
            tipo_cuenta_normalized = 'Corriente'
        else:
            tipo_cuenta_normalized = 'Ahorro'
            
    # Normalize Banco name
    banco_lower = banco_normalized.lower()
    if 'reserva' in banco_lower:
        banco_normalized = 'Banreservas'
    elif 'popular' in banco_lower:
        banco_normalized = 'Banco Popular'
    elif 'bhd' in banco_lower:
        banco_normalized = 'Banco BHD'
    elif 'scotia' in banco_lower:
        banco_normalized = 'Scotiabank'
    elif 'santa cruz' in banco_lower:
        banco_normalized = 'Banco Santa Cruz'
    elif not banco_normalized:
        banco_normalized = 'Banreservas'  # Default safe bank
        
    return banco_normalized, tipo_cuenta_normalized

# Helper to find elements ignoring XML namespace
def find_elem_ignore_ns(parent, tag_name):
    for elem in parent:
        if elem.tag.endswith('}' + tag_name) or elem.tag == tag_name:
            return elem
    return None

def findall_elems_ignore_ns(parent, tag_name):
    results = []
    for elem in parent:
        if elem.tag.endswith('}' + tag_name) or elem.tag == tag_name:
            results.append(elem)
    return results

def findall_recursive_ignore_ns(parent, tag_name):
    results = []
    def rec(node):
        if node.tag.endswith('}' + tag_name) or node.tag == tag_name:
            results.append(node)
        for child in node:
            rec(child)
    rec(parent)
    return results

# Clean XML element extraction
def get_cell_value(c_elem, shared_strings):
    t = c_elem.get('t')
    v_elem = find_elem_ignore_ns(c_elem, 'v')
    is_elem = find_elem_ignore_ns(c_elem, 'is')
    
    val = ""
    if v_elem is not None and v_elem.text is not None:
        val = v_elem.text
        if t == 's':
            try:
                val = shared_strings[int(val)]
            except:
                pass
    elif is_elem is not None:
        t_elem = find_elem_ignore_ns(is_elem, 't')
        if t_elem is not None and t_elem.text is not None:
            val = t_elem.text
    return val

# Main parser for XLSX using zipfile
def parse_xlsx(file_path):
    rows = {}
    with zipfile.ZipFile(file_path, 'r') as zip_ref:
        # Read shared strings
        shared_strings = []
        try:
            sst_xml = zip_ref.read('xl/sharedStrings.xml')
            root = ET.fromstring(sst_xml)
            for si in findall_recursive_ignore_ns(root, 'si'):
                t_nodes = findall_recursive_ignore_ns(si, 't')
                val = "".join([t.text for t in t_nodes if t.text is not None])
                shared_strings.append(val)
        except Exception as e:
            pass

        # Read sheet1
        sheet_xml = zip_ref.read('xl/worksheets/sheet1.xml')
        root = ET.fromstring(sheet_xml)
        
        for row_elem in findall_recursive_ignore_ns(root, 'row'):
            row_num = int(row_elem.get('r'))
            row_data = {}
            for c_elem in findall_recursive_ignore_ns(row_elem, 'c'):
                ref = c_elem.get('r')
                col_letter = re.match(r'^([A-Z]+)', ref).group(1)
                val = get_cell_value(c_elem, shared_strings)
                row_data[col_letter] = val
            rows[row_num] = row_data
            
    return rows

def clean_cedula(val):
    if not val:
        return ""
    return str(val).replace('-', '').replace(' ', '').strip()

def process_file(file_path):
    warnings = []
    records = []
    
    try:
        if file_path.endswith('.xlsx'):
            rows = parse_xlsx(file_path)
        else:
            # Fallback to CSV parser if not xlsx
            rows = {}
            with open(file_path, mode='r', encoding='utf-8') as f:
                reader = csv.reader(f)
                for i, row in enumerate(reader):
                    row_num = i + 1
                    row_data = {}
                    for col_idx, cell_val in enumerate(row):
                        # Convert column index to letters
                        col_letter = ""
                        temp = col_idx
                        while temp >= 0:
                            col_letter = chr(temp % 26 + 65) + col_letter
                            temp = temp // 26 - 1
                        row_data[col_letter] = cell_val
                    rows[row_num] = row_data
                    
        if not rows:
            return {"exito": False, "mensaje": "El archivo no contiene filas o no se pudo leer."}
            
        # Detect headers
        header_row = rows.get(1, {})
        # Map headers to column letters
        col_mappings = {}
        for col, val in header_row.items():
            val_clean = str(val).strip().lower()
            col_mappings[val_clean] = col
            
        # Helper to get column letter dynamically by partial header match
        def get_col(header_patterns, default_col):
            for pattern in header_patterns:
                for clean_h, col in col_mappings.items():
                    if pattern in clean_h:
                        return col
            return default_col

        # Resolve dynamic headers based on search patterns
        col_pdv = get_col(["código de punto de venta", "codigo pdv", "punto de venta"], "B")
        col_sup = get_col(["supervisor"], "C")
        col_name = get_col(["nombres y apellidos", "nombre"], "F")
        col_cedula = get_col(["cedula", "cédula", "rnc"], "G")
        col_location = get_col(["ubicacion", "ubicación"], "H")
        col_birthday = get_col(["fecha de cumpleaños", "fecha de cumplea", "cumpleaños", "nacimiento"], "J")
        col_email = get_col(["correo", "email"], "N")
        col_phone = get_col(["telefono", "teléfono", "whatsapp"], "L")
        col_banco = get_col(["banco"], "T")
        col_tipo_cta = get_col(["tipo de cuenta", "tipo cuenta"], "U")
        col_num_cta = get_col(["número de cuenta", "numero de cuenta", "numero cuenta", "cuenta"], "V")
        
        # Commission columns
        col_com_ley = get_col(["autos seguros ley", "autos ley"], "AC")
        col_com_full = get_col(["autos seguros full", "autos full"], "AD")
        col_com_fianzas = get_col(["fianzas"], "AE")
        col_com_incendio = get_col(["incendio"], "AF")
        col_com_rc = get_col(["responsabilidad civil", "civil", "rc"], "AG")
        col_com_otros = get_col(["otros"], "AH")

        # Loop through rows starting from index 2
        for row_num in sorted(rows.keys()):
            if row_num == 1:
                continue
                
            row = rows[row_num]
            # Ensure row has data before processing
            if not any(row.values()):
                continue
                
            raw_pdv_code = row.get(col_pdv, '').strip()
            raw_name = row.get(col_name, '').strip()
            
            # Skip if name and code are empty (noise rows)
            if not raw_name and not raw_pdv_code:
                continue
                
            raw_cedula = row.get(col_cedula, '').strip()
            raw_email = row.get(col_email, '').strip()
            raw_phone = row.get(col_phone, '').strip()
            raw_location = row.get(col_location, '').strip()
            raw_birthday = row.get(col_birthday, '').strip()
            raw_supervisor = row.get(col_sup, '').strip()
            
            raw_banco = row.get(col_banco, '').strip()
            raw_tipo_cta = row.get(col_tipo_cta, '').strip()
            raw_num_cta = row.get(col_num_cta, '').strip()
            
            # Apply strict Reservas normalization rules
            banco_clean, tipo_cta_clean = normalize_bank_and_account_type(raw_banco, raw_tipo_cta)
            
            # Smart splits for name and supervisor
            first_name, last_name = split_name(raw_name)
            
            # Clean dates and percentages
            birthday_clean = parse_spanish_date(raw_birthday)
            if raw_birthday and not birthday_clean:
                warnings.append(f"Fila {row_num}: No se pudo parsear la fecha de cumpleaños '{raw_birthday}'")
                
            com_ley = clean_commission(row.get(col_com_ley, '0'))
            com_full = clean_commission(row.get(col_com_full, '0'))
            com_fianzas = clean_commission(row.get(col_com_fianzas, '0'))
            com_incendio = clean_commission(row.get(col_com_incendio, '0'))
            com_rc = clean_commission(row.get(col_com_rc, '0'))
            com_otros = clean_commission(row.get(col_com_otros, '0'))
            
            # Formulate record dictionary
            record = {
                "fila": row_num,
                "codigo_usuario": raw_pdv_code,
                "username": raw_pdv_code if raw_pdv_code else raw_email.split('@')[0],
                "cedula": clean_cedula(raw_cedula),
                "nombre": first_name,
                "apellido": last_name,
                "email": raw_email if raw_email else f"{raw_pdv_code.lower()}@masquefianzas.com.do",
                "telefono": raw_phone,
                "ubicacion": raw_location,
                "fecha_cumpleanos": birthday_clean,
                "banco": banco_clean,
                "tipo_cuenta": tipo_cta_clean,
                "numero_cuenta": raw_num_cta,
                "supervisor_texto": raw_supervisor,
                "perfil_id": 5, # Socio Comercial PDV
                "es_comisionante": 1,
                "comision_autos_ley": com_ley,
                "comision_autos_full": com_full,
                "comision_fianzas": com_fianzas,
                "comision_incendio": com_incendio,
                "comision_rc": com_rc,
                "comision_otros": com_otros
            }
            records.append(record)
            
        return {
            "exito": True,
            "registros": records,
            "warnings": warnings,
            "mensaje": f"Procesados {len(records)} registros exitosamente."
        }
        
    except Exception as e:
        return {"exito": False, "mensaje": f"Error general en ETL: {str(e)}"}

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"exito": False, "mensaje": "Falta la ruta del archivo de entrada."}))
        sys.exit(1)
        
    file_path = sys.argv[1]
    res = process_file(file_path)
    print(json.dumps(res, indent=2))
