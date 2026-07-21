# Vendor-Management-System-Oracle-sql-developer
Designed and developed a normalized relational database and PHP-based web application for managing supplier records, products, purchase orders, contracts, and payment workflows. Implemented stored procedures, triggers, constraints, and transactional queries to enforce data integrity and ensure accurate, reliable vendor and transaction management.


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



sqlplus student1/pass123@//localhost:1521/ORCLPDB

sqlplus student1/pass123@//localhost:1521/ORCLPDB

sqlplus student1/pass123@localhost:1521/ORCLPDB
