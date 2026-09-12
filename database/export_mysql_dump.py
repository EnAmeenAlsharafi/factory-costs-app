import sqlite3
import re

db_path = r"C:\Users\ename\.gemini\antigravity-ide\scratch\factory-costs-app\database\database.sqlite"
sql_dump_path = r"C:\Users\ename\.gemini\antigravity-ide\scratch\factory-costs-app\database\factory_costs.sql"

conn = sqlite3.connect(db_path)
cursor = conn.cursor()

# Get all table names
cursor.execute("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
tables = cursor.fetchall()

with open(sql_dump_path, 'w', encoding='utf-8') as f:
    f.write("-- ========================================================\n")
    f.write("-- قاعدة بيانات نظام إدارة تكاليف المصنع\n")
    f.write("-- Factory Production Cost Management Database Dump\n")
    f.write("-- Compatible with MySQL / MariaDB (Hostinger)\n")
    f.write("-- ========================================================\n\n")
    f.write("SET FOREIGN_KEY_CHECKS = 0;\n\n")

    for table_name, create_sql in tables:
        f.write(f"-- --------------------------------------------------------\n")
        f.write(f"-- Table structure for `{table_name}`\n")
        f.write(f"-- --------------------------------------------------------\n")
        f.write(f"DROP TABLE IF EXISTS `{table_name}`;\n")
        
        # Convert SQLite CREATE TABLE to standard MySQL
        mysql_create = create_sql
        mysql_create = mysql_create.replace('"id" integer primary key autoincrement not null', '`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY')
        mysql_create = mysql_create.replace('"id" integer not null primary key autoincrement', '`id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY')
        mysql_create = mysql_create.replace('autoincrement', 'AUTO_INCREMENT')
        mysql_create = re.sub(r'"(\w+)"', r'`\1`', mysql_create)
        
        f.write(mysql_create + " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n")

        # Dump table data
        cursor.execute(f"SELECT * FROM `{table_name}`")
        rows = cursor.fetchall()
        if rows:
            cursor.execute(f"PRAGMA table_info(`{table_name}`)")
            columns = [col[1] for col in cursor.fetchall()]
            cols_str = ", ".join([f"`{c}`" for c in columns])
            
            f.write(f"-- Data for `{table_name}`\n")
            f.write(f"INSERT INTO `{table_name}` ({cols_str}) VALUES\n")
            row_lines = []
            for r in rows:
                vals = []
                for val in r:
                    if val is None:
                        vals.append("NULL")
                    elif isinstance(val, (int, float)):
                        vals.append(str(val))
                    else:
                        val_str = str(val).replace("'", "''").replace("\\", "\\\\")
                        vals.append(f"'{val_str}'")
                row_lines.append(f"({', '.join(vals)})")
            f.write(",\n".join(row_lines) + ";\n\n")

    f.write("SET FOREIGN_KEY_CHECKS = 1;\n")

print("SQL dump created at:", sql_dump_path)
