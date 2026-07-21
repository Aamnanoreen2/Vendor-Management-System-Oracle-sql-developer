# Vendor-Management-System-Oracle-sql-developer
Designed and developed a normalized relational database and PHP-based web application for managing supplier records, products, purchase orders, contracts, and payment workflows. Implemented stored procedures, triggers, constraints, and transactional queries to enforce data integrity and ensure accurate, reliable vendor and transaction management.
<img width="1853" height="963" alt="image" src="https://github.com/user-attachments/assets/00d64a56-352a-46c5-8e42-50f0f37bd049" />
<img width="1847" height="888" alt="image" src="https://github.com/user-attachments/assets/f81a521a-e8d5-47f5-9d2c-99e1a0e327a4" />
<img width="1810" height="870" alt="image" src="https://github.com/user-attachments/assets/591a3165-db2b-4fdd-91aa-3a3205a86f04" />


1. Check PDB status

Run:

SHOW PDBS;

You’ll probably see something like:

NAME       OPEN MODE
---------- ----------
ORCLPDB    MOUNTED
2. Open the PDB

Run:

ALTER PLUGGABLE DATABASE ORCLPDB OPEN;
3. Verify

Run again:

SHOW PDBS;

Now it should say:

ORCLPDB    READ WRITE
4. (Important) Save it so it auto-opens next time
ALTER PLUGGABLE DATABASE ORCLPDB SAVE STATE;
🧪 Optional: Switch into the PDB

You can also test directly:

ALTER SESSION SET CONTAINER = ORCLPDB;

Then:

SELECT name FROM v$database;
🔁 After that

Go back and refresh your PHP app — your error should be gone.

⚠️ One small note

This command you tried:

show conn

is invalid in SQL*Plus (that’s why the error). If you want container info, use:

SHOW CON_NAME;

Bottom line
CDB = ✅ already open
PDB (ORCLPDB) = ❌ not open → this is your problem
Fix = ALTER PLUGGABLE DATABASE ORCLPDB OPEN;

<img width="1716" height="926" alt="image" src="https://github.com/user-attachments/assets/db03a69f-197e-4e76-9c7c-8effb50d9df9" />


sqlplus student1/pass123@//localhost:1521/ORCLPDB

sqlplus student1/pass123@//localhost:1521/ORCLPDB

sqlplus student1/pass123@localhost:1521/ORCLPDB


for schema within oracle sql developer 

Go to File
 → Data Modeler
 → Import
 → Data Dictionary
Right click diagram → Layout → Auto Layout
