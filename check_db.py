import pymysql
import json
conn = pymysql.connect(host='localhost', user='root', password='', database='masque_fianzas_integrada_01')
cursor = conn.cursor(pymysql.cursors.DictCursor)
cursor.execute('SELECT * FROM modulos')
print("MODULOS:")
print(json.dumps(cursor.fetchall(), indent=2))
cursor.execute('SELECT * FROM funciones_modulo')
print("\nFUNCIONES:")
print(json.dumps(cursor.fetchall(), indent=2))
