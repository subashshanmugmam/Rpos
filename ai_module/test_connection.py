
import mysql.connector

print("Attempting direct connection to XAMPP MySQL on port 3307...")
try:
    conn = mysql.connector.connect(
        host='127.0.0.1',
        port=3307,
        user='root',
        password='',
        database='retail_pos'
    )
    print("✅ Connection successful!")
    
    cursor = conn.cursor()
    cursor.execute("SELECT count(*) FROM sales_transactions")
    result = cursor.fetchone()
    print(f"Number of sales transactions: {result[0]}")
    
    cursor.close()
    conn.close()
except Exception as e:
    print(f"❌ Connection failed: {e}")
