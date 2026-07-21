SHOW USER;
ALTER SESSION SET CONTAINER = CDB$ROOT;


ALTER USER vms_user QUOTA UNLIMITED ON USERS;



-- Check current user
SHOW USER;

-- Drop trigger
BEGIN
    EXECUTE IMMEDIATE 'DROP TRIGGER VMS_Payment_Trigger';
EXCEPTION WHEN OTHERS THEN NULL;
END;
/

-- Drop procedure
BEGIN
    EXECUTE IMMEDIATE 'DROP PROCEDURE VMS_Add_Vendor';
EXCEPTION WHEN OTHERS THEN NULL;
END;
/

-- Drop function
BEGIN
    EXECUTE IMMEDIATE 'DROP FUNCTION VMS_Get_Order_Total';
EXCEPTION WHEN OTHERS THEN NULL;
END;
/

-- Drop view
BEGIN
    EXECUTE IMMEDIATE 'DROP VIEW VMS_Order_Summary';
EXCEPTION WHEN OTHERS THEN NULL;
END;
/

-- Drop tables (child → parent order)
BEGIN EXECUTE IMMEDIATE 'DROP TABLE VMS_Payments CASCADE CONSTRAINTS'; EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE VMS_OrderDetails CASCADE CONSTRAINTS'; EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE VMS_Orders CASCADE CONSTRAINTS'; EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE VMS_VendorProducts CASCADE CONSTRAINTS'; EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE VMS_Products CASCADE CONSTRAINTS'; EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE VMS_Vendors CASCADE CONSTRAINTS'; EXCEPTION WHEN OTHERS THEN NULL; END;
/

-- Drop sequences
BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE vms_vendor_seq'; EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE vms_product_seq'; EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE vms_order_seq'; EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE vms_order_detail_seq'; EXCEPTION WHEN OTHERS THEN NULL; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE vms_payment_seq'; EXCEPTION WHEN OTHERS THEN NULL; END;
/
-- =========================================
-- VMS CLEAN DATABASE SCRIPT (RENAMED VERSION)
-- =========================================

-- =========================
-- 1. TABLES
-- =========================

CREATE TABLE VMS_Vendors (
    vendor_id NUMBER PRIMARY KEY,
    vendor_name VARCHAR2(100) NOT NULL,
    email VARCHAR2(100) UNIQUE,
    phone VARCHAR2(15),
    city VARCHAR2(50),
    status       VARCHAR2(20) DEFAULT 'Active'
);

CREATE TABLE VMS_Products (
    product_id NUMBER PRIMARY KEY,
    product_name VARCHAR2(100) NOT NULL,
    category VARCHAR2(50),
    price NUMBER(10,2) CHECK (price > 0)
);

CREATE TABLE VMS_VendorProducts (
    vendor_id NUMBER,
    product_id NUMBER,
    supply_price NUMBER(10,2),

    PRIMARY KEY (vendor_id, product_id),
    FOREIGN KEY (vendor_id) REFERENCES VMS_Vendors(vendor_id),
    FOREIGN KEY (product_id) REFERENCES VMS_Products(product_id)
);


CREATE TABLE VMS_Users_Employees (
    user_id          NUMBER PRIMARY KEY,
    full_name        VARCHAR2(100) NOT NULL,
    email            VARCHAR2(100) UNIQUE,
    department       VARCHAR2(50),
    role             VARCHAR2(30),           -- e.g., 'Purchaser', 'Manager', 'Admin'
    created_date     DATE DEFAULT SYSDATE
);

CREATE TABLE VMS_Orders (
    order_id          NUMBER PRIMARY KEY,
    vendor_id         NUMBER NOT NULL,
    placed_by_user_id NUMBER,
    order_date        DATE DEFAULT SYSDATE,
    status            VARCHAR2(20) DEFAULT 'Pending' 
                      CHECK (status IN ('Pending','Delivered','Cancelled','Approved')),
    FOREIGN KEY (vendor_id) REFERENCES VMS_Vendors(vendor_id),
    FOREIGN KEY (placed_by_user_id) REFERENCES VMS_Users_Employees(user_id)
);
--CREATE TABLE VMS_Orders (
--    order_id NUMBER PRIMARY KEY,
--    vendor_id NUMBER,
--    order_date DATE DEFAULT SYSDATE,
--    status VARCHAR2(20) CHECK (status IN ('Pending','Delivered','Cancelled')),
--
--    FOREIGN KEY (vendor_id) REFERENCES VMS_Vendors(vendor_id)
--);


-- Then alter Orders table:
--ALTER TABLE VMS_Orders 
--ADD (placed_by_user_id NUMBER);
--
--ALTER TABLE VMS_Orders 
--ADD CONSTRAINT fk_orders_placed_by 
--FOREIGN KEY (placed_by_user_id) REFERENCES VMS_Users_Employees(user_id);
--
--select * from vms_orders;
--desc vms_orders;


CREATE TABLE VMS_OrderDetails (
    order_detail_id NUMBER PRIMARY KEY,
    order_id NUMBER,
    product_id NUMBER,
    quantity NUMBER CHECK (quantity > 0),

    FOREIGN KEY (order_id) REFERENCES VMS_Orders(order_id),
    FOREIGN KEY (product_id) REFERENCES VMS_Products(product_id)
);


CREATE TABLE VMS_Payments (
    payment_id     NUMBER PRIMARY KEY,
    order_id       NUMBER NOT NULL,
    amount         NUMBER(10,2) CHECK (amount > 0),
    payment_date   DATE DEFAULT SYSDATE,
    FOREIGN KEY (order_id) REFERENCES VMS_Orders(order_id)
);
--CREATE TABLE VMS_Payments (
--    payment_id NUMBER PRIMARY KEY,
--    order_id NUMBER,
--    amount NUMBER(10,2),
--    payment_date DATE,
--
--    FOREIGN KEY (order_id) REFERENCES VMS_Orders(order_id)
--);





--CREATE TABLE VMS_VendorContracts (
--    contract_id         NUMBER PRIMARY KEY,
--    vendor_id           NUMBER NOT NULL,
--    contract_number     VARCHAR2(50) UNIQUE,
--    start_date          DATE NOT NULL,
--    end_date            DATE,
--    payment_terms       VARCHAR2(100),       -- e.g., 'Net 30 days'
--    discount_percentage NUMBER(5,2),
--    contract_value      NUMBER(12,2),
--    status              VARCHAR2(20) CHECK (status IN ('Active','Expired','Terminated')),
--    document_path       VARCHAR2(500),       -- path to uploaded contract PDF
--    
--    CONSTRAINT fk_contract_vendor 
--        FOREIGN KEY (vendor_id) REFERENCES VMS_Vendors(vendor_id)
--);

CREATE TABLE VMS_VendorContracts (
    contract_id          NUMBER PRIMARY KEY,
    vendor_id            NUMBER NOT NULL,
    contract_number      VARCHAR2(50) UNIQUE NOT NULL,
    start_date           DATE NOT NULL,
    end_date             DATE,
    payment_terms        VARCHAR2(100),           -- e.g., 'Net 30 days', 'Advance Payment'
    discount_percentage  NUMBER(5,2) CHECK (discount_percentage BETWEEN 0 AND 100),
    contract_value       NUMBER(12,2) CHECK (contract_value >= 0),
    status               VARCHAR2(20) DEFAULT 'Active' 
                         CHECK (status IN ('Active', 'Expired', 'Terminated')),
    document_path        VARCHAR2(500),           -- path or URL to contract file
    
    CONSTRAINT fk_contract_vendor 
        FOREIGN KEY (vendor_id) REFERENCES VMS_Vendors(vendor_id)
);

select *from VMS_VendorContracts;



CREATE TABLE VMS_VendorPerformance (
    performance_id   NUMBER PRIMARY KEY,
    vendor_id        NUMBER NOT NULL,
    order_id         NUMBER,                    -- optional link to specific order
    rating           NUMBER(2,1) CHECK (rating BETWEEN 1 AND 5),  -- 1 to 5 stars
    review_comments  VARCHAR2(500),
    review_date      DATE DEFAULT SYSDATE,
    
    CONSTRAINT fk_performance_vendor 
        FOREIGN KEY (vendor_id) REFERENCES VMS_Vendors(vendor_id),
    CONSTRAINT fk_performance_order 
        FOREIGN KEY (order_id) REFERENCES VMS_Orders(order_id)
);
 select * from VMS_VendorPerformance;

-- Basic Inventory Table (Recommended for your VMS)
CREATE TABLE VMS_Inventory (
    inventory_id        NUMBER PRIMARY KEY,
    product_id          NUMBER NOT NULL,
    quantity_in_stock   NUMBER(10, 0) DEFAULT 0 CHECK (quantity_in_stock >= 0),
    reorder_level       NUMBER(10, 0) DEFAULT 10 CHECK (reorder_level >= 0),
    last_updated        DATE DEFAULT SYSDATE,
    
    CONSTRAINT fk_inventory_product 
        FOREIGN KEY (product_id) REFERENCES VMS_Products(product_id)
);

ALTER TABLE VMS_INVENTORY ADD CONSTRAINT UNIQUE_PROD_INV UNIQUE (PRODUCT_ID);

select * from VMS_Inventory;

-- Sequence for Inventory (Oracle best practice)
CREATE SEQUENCE VMS_INVENTORY_SEQ 
    START WITH 1 
    INCREMENT BY 1 
    NOCACHE;
    
    
    
    CREATE INDEX idx_orders_vendor_status ON VMS_Orders(vendor_id, status);
    
    
    
   SELECT 
    ui.index_name,
    ui.table_name,
    ui.status,
    ui.index_type,
    LISTAGG(uic.column_name, ', ') WITHIN GROUP (ORDER BY uic.column_position) AS "COLUMNS"
FROM user_indexes ui
JOIN user_ind_columns uic ON ui.index_name = uic.index_name
WHERE ui.table_name = 'VMS_ORDERS'
GROUP BY ui.index_name, ui.table_name, ui.status, ui.index_type
ORDER BY ui.index_name;


CREATE INDEX idx_orderdetails_order ON VMS_OrderDetails(order_id);
CREATE INDEX idx_vendorproducts_vendor ON VMS_VendorProducts(vendor_id);

    

SELECT 
    ui.table_name,
    ui.index_name,
    ui.status,
    ui.index_type,
    ui.uniqueness,
    LISTAGG(uic.column_name, ', ') WITHIN GROUP (ORDER BY uic.column_position) AS columns
FROM user_indexes ui
JOIN user_ind_columns uic ON ui.index_name = uic.index_name
WHERE ui.index_name IN ('IDX_ORDERDETAILS_ORDER', 'IDX_VENDORPRODUCTS_VENDOR')
GROUP BY ui.table_name, ui.index_name, ui.status, ui.index_type, ui.uniqueness
ORDER BY ui.table_name, ui.index_name;

SELECT 
    ui.table_name,
    ui.index_name,
    ui.status,
    LISTAGG(uic.column_name, ', ') WITHIN GROUP (ORDER BY uic.column_position) AS columns
FROM user_indexes ui
JOIN user_ind_columns uic ON ui.index_name = uic.index_name
WHERE ui.table_name IN ('VMS_ORDERS', 'VMS_ORDERDETAILS', 'VMS_VENDORPRODUCTS', 'VMS_INVENTORY')
GROUP BY ui.table_name, ui.index_name, ui.status
ORDER BY ui.table_name, ui.index_name;


SELECT 
    ui.index_name,
    ui.index_type,
    ui.status,
    ui.uniqueness,
    LISTAGG(uic.column_name, ', ') WITHIN GROUP (ORDER BY uic.column_position) AS columns
FROM user_indexes ui
JOIN user_ind_columns uic ON ui.index_name = uic.index_name
WHERE ui.table_name = 'VMS_INVENTORY'
GROUP BY ui.index_name, ui.index_type, ui.status, ui.uniqueness
ORDER BY ui.index_name;
    

-- =========================
-- 2. SEQUENCES
-- =========================

--CREATE SEQUENCE vms_vendor_seq START WITH 1 INCREMENT BY 1;
--CREATE SEQUENCE vms_product_seq START WITH 100 INCREMENT BY 1;
--CREATE SEQUENCE vms_order_seq START WITH 1 INCREMENT BY 1;
--CREATE SEQUENCE vms_order_detail_seq START WITH 1000 INCREMENT BY 1;
--CREATE SEQUENCE vms_payment_seq START WITH 500 INCREMENT BY 1;

CREATE SEQUENCE VMS_VENDOR_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE VMS_PRODUCT_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE VMS_ORDER_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE VMS_ORDER_DETAIL_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE VMS_PAYMENT_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE VMS_USER_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE VMS_CONTRACT_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE VMS_PERFORMANCE_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;
CREATE SEQUENCE VMS_INVENTORY_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;

-- =========================
-- 3. SAMPLE DATA - without sequence
-- =========================

--INSERT INTO VMS_Vendors VALUES (1,'Ali Traders','ali@gmail.com','03001234567','Lahore');
--INSERT INTO VMS_Vendors VALUES (2,'Tech Hub','techhub@gmail.com','03111234567','Karachi');
--
--INSERT INTO VMS_Products VALUES (101,'Laptop','Electronics',80000);
--INSERT INTO VMS_Products VALUES (102,'Mouse','Accessories',1500);
--
--INSERT INTO VMS_VendorProducts VALUES (1,101,75000);
--INSERT INTO VMS_VendorProducts VALUES (2,102,1200);
--
--INSERT INTO VMS_Orders VALUES (1,1,SYSDATE,'Pending');
--INSERT INTO VMS_Orders VALUES (2,2,SYSDATE,'Delivered');
--
--INSERT INTO VMS_OrderDetails VALUES (1,1,101,2);
--INSERT INTO VMS_OrderDetails VALUES (2,2,102,5);
--
--INSERT INTO VMS_Payments VALUES (1,1,160000,SYSDATE);
--INSERT INTO VMS_Payments VALUES (2,2,6000,SYSDATE);
--INSERT INTO VMS_Payments VALUES (2,2,5000,SYSDATE);




-- =========================
-- 5. SAMPLE DATA (Using Sequences - Clean & Correct)
-- =========================

-- Users
INSERT INTO VMS_Users_Employees (user_id, full_name, email, department, role) 
VALUES (VMS_USER_SEQ.NEXTVAL, 'Aamna Noreen', 'aamna@example.com', 'Procurement', 'Purchaser');

-- Vendors
INSERT INTO VMS_Vendors (vendor_id, vendor_name, email, phone, city) 
VALUES (VMS_VENDOR_SEQ.NEXTVAL, 'Ali Traders', 'ali@gmail.com', '03001234567', 'Lahore');
select * from VMS_Vendors;

INSERT INTO VMS_Vendors (vendor_id, vendor_name, email, phone, city) 
VALUES (VMS_VENDOR_SEQ.NEXTVAL, 'Tech Hub', 'techhub@gmail.com', '03111234567', 'Karachi');

INSERT INTO VMS_Vendors (vendor_id, vendor_name, email, phone, city)
VALUES (vms_vendor_seq.NEXTVAL, 'Office Supplies Co', 'office@gmail.com', '03221234567', 'Islamabad');

INSERT INTO VMS_Vendors (vendor_id, vendor_name, email, phone, city,status)
VALUES (vms_vendor_seq.NEXTVAL, 'AmyTech', 'amytech@gmail.com', '03221134477', 'Islamabad','Expired');

-- Products
INSERT INTO VMS_Products (product_id, product_name, category, price) 
VALUES (VMS_PRODUCT_SEQ.NEXTVAL, 'Laptop', 'Electronics', 80000);

INSERT INTO VMS_Products (product_id, product_name, category, price) 
VALUES (VMS_PRODUCT_SEQ.NEXTVAL, 'Mouse', 'Accessories', 1500);

INSERT INTO VMS_Products (product_id, product_name, category, price) 
VALUES (VMS_PRODUCT_SEQ.NEXTVAL, 'Keyboard', 'Accessories', 3000);

-- Vendor Products
INSERT INTO VMS_VendorProducts (vendor_id, product_id, supply_price) 
VALUES (1, 1, 75000); --Ali Traders supplies Laptop @ 75,000

INSERT INTO VMS_VendorProducts (vendor_id, product_id, supply_price) 
VALUES (2, 2, 1200);  ---- Tech Hub supplies Mouse @ 1,200

INSERT INTO VMS_VendorProducts (vendor_id, product_id, supply_price)
VALUES (1, 2, 1400);      -- Ali Traders also supplies Mouse @ 1,400

INSERT INTO VMS_VendorProducts (vendor_id, product_id, supply_price)
VALUES (2, 3, 2800);      -- Tech Hub supplies Keyboard @ 2,800

INSERT INTO VMS_VendorProducts (vendor_id, product_id, supply_price)
VALUES (3, 3, 2900);      -- Office Supplies Co supplies Keyboard @ 2,900


-- 1. Simple View of Vendor Products
SELECT 
    v.vendor_name,
    p.product_name,
    p.category,
    vp.supply_price,
    p.price AS market_price
FROM VMS_VendorProducts vp
JOIN VMS_Vendors v ON vp.vendor_id = v.vendor_id
JOIN VMS_Products p ON vp.product_id = p.product_id
ORDER BY v.vendor_name, p.product_name;

-- 2. Which products each vendor supplies
SELECT 
    v.vendor_name,
    LISTAGG(p.product_name, ', ') WITHIN GROUP (ORDER BY p.product_name) AS products_supplied
FROM VMS_VendorProducts vp
JOIN VMS_Vendors v ON vp.vendor_id = v.vendor_id
JOIN VMS_Products p ON vp.product_id = p.product_id
GROUP BY v.vendor_name;




-- Orders
INSERT INTO VMS_Orders (order_id, vendor_id, placed_by_user_id, status) 
VALUES (VMS_ORDER_SEQ.NEXTVAL, 1, 1, 'Pending');

INSERT INTO VMS_Orders (order_id, vendor_id, placed_by_user_id, status) 
VALUES (VMS_ORDER_SEQ.NEXTVAL, 2, 1, 'Delivered');

-- Order Details
INSERT INTO VMS_OrderDetails (order_detail_id, order_id, product_id, quantity) 
VALUES (VMS_ORDER_DETAIL_SEQ.NEXTVAL, 1, 1, 2);

INSERT INTO VMS_OrderDetails (order_detail_id, order_id, product_id, quantity) 
VALUES (VMS_ORDER_DETAIL_SEQ.NEXTVAL, 2, 2, 5);

-- Payments
INSERT INTO VMS_Payments (payment_id, order_id, amount) 
VALUES (VMS_PAYMENT_SEQ.NEXTVAL, 1, 160000);

INSERT INTO VMS_Payments (payment_id, order_id, amount) 
VALUES (VMS_PAYMENT_SEQ.NEXTVAL, 2, 7500);


INSERT INTO VMS_Inventory (inventory_id, product_id, quantity_in_stock, reorder_level) 
VALUES (VMS_INVENTORY_SEQ.NEXTVAL, 1, 50, 10);

INSERT INTO VMS_Inventory (inventory_id, product_id, quantity_in_stock, reorder_level) 
VALUES (VMS_INVENTORY_SEQ.NEXTVAL, 2, 200, 20);

INSERT INTO VMS_Inventory (inventory_id, product_id, quantity_in_stock, reorder_level) 
VALUES (VMS_INVENTORY_SEQ.NEXTVAL, 3, 80, 15);

---===============C O N T I N U E D =========
-- =========================
-- SAMPLE DATA FOR VMS_VendorContracts
-- =========================

INSERT INTO VMS_VendorContracts 
(contract_id, vendor_id, contract_number, start_date, end_date, payment_terms, 
 discount_percentage, contract_value, status, document_path)
VALUES 
(VMS_CONTRACT_SEQ.NEXTVAL, 1, 'CNT-2026-001', 
 DATE '2026-01-01', DATE '2027-12-31', 'Net 30 days', 
 10, 5000000, 'Active', 
 'D:\VMS_Contracts\AliTraders_Contract_2026.pdf');

INSERT INTO VMS_VendorContracts 
(contract_id, vendor_id, contract_number, start_date, end_date, payment_terms, 
 discount_percentage, contract_value, status, document_path)
VALUES 
(VMS_CONTRACT_SEQ.NEXTVAL, 2, 'CNT-2026-002', 
 DATE '2026-03-01', DATE '2027-02-28', 'Advance Payment', 
 5, 1200000, 'Active', 
 'D:\VMS_Contracts\TechHub_Agreement_2026.pdf');

INSERT INTO VMS_VendorContracts 
(contract_id, vendor_id, contract_number, start_date, end_date, payment_terms, 
 discount_percentage, contract_value, status, document_path)
VALUES 
(VMS_CONTRACT_SEQ.NEXTVAL, 3, 'CNT-2026-003', 
 DATE '2026-02-15', DATE '2026-08-15', 'Net 15 days', 
 0, 450000, 'Expired', 
 'D:\VMS_Contracts\OfficeSupplies_Contract.pdf');



-- First, check which rows will be deleted
SELECT * FROM VMS_VendorContracts 
WHERE status = 'Expired';

-- Delete the expired contracts
DELETE FROM VMS_VendorContracts 
WHERE status = 'Expired';

COMMIT;

-- Verify after deletion
SELECT * FROM VMS_VendorContracts;

-- Reset the sequence to start from 1 again
ALTER SEQUENCE VMS_CONTRACT_SEQ RESTART START WITH 3;

-- Verify the next value
SELECT VMS_CONTRACT_SEQ.NEXTVAL FROM DUAL;

SELECT * FROM VMS_VendorContracts;

-- =========================
-- SAMPLE DATA FOR VMS_VendorPerformance
-- =========================

INSERT INTO VMS_VendorPerformance 
(performance_id, vendor_id, order_id, rating, review_comments, review_date)
VALUES 
(VMS_PERFORMANCE_SEQ.NEXTVAL, 1, 1, 4.5, 
 'Good quality products, delivered on time.', SYSDATE);

INSERT INTO VMS_VendorPerformance 
(performance_id, vendor_id, order_id, rating, review_comments, review_date)
VALUES 
(VMS_PERFORMANCE_SEQ.NEXTVAL, 2, 2, 3.0, 
 'Delivery was delayed by 3 days. Quality is average.', 
 DATE '2026-04-20');

INSERT INTO VMS_VendorPerformance 
(performance_id, vendor_id, order_id, rating, review_comments, review_date)
VALUES 
(VMS_PERFORMANCE_SEQ.NEXTVAL, 1, NULL, 5.0, 
 'Excellent long-term vendor. Highly recommended.', 
 DATE '2026-04-25');



INSERT INTO VMS_VendorPerformance 
(performance_id, vendor_id, order_id, rating, review_comments, review_date)
VALUES 
(VMS_PERFORMANCE_SEQ.NEXTVAL, 3, NULL, 4.0, 
 'Reliable for office supplies but price is slightly high.', 
 SYSDATE);

-- First, check how many rows will be deleted (always good practice)
SELECT * FROM VMS_VendorPerformance 
WHERE order_id IS NULL;

-- Delete the rows where order_id is NULL
DELETE FROM VMS_VendorPerformance 
WHERE order_id IS NULL;

-- Commit the changes
COMMIT;

-- Verify after deletion
SELECT * FROM VMS_VendorPerformance;

-- Reset the sequence to start from 1 again
ALTER SEQUENCE VMS_PERFORMANCE_SEQ RESTART START WITH 3;

-- Verify the next value
SELECT VMS_PERFORMANCE_SEQ.NEXTVAL FROM DUAL;

SELECT performance_id, vendor_id, order_id, rating, review_comments 
FROM VMS_VendorPerformance;

-- Update all rows where order_id is NULL
UPDATE VMS_VendorPerformance
SET order_id = 1          -- Change this to the order_id you want
WHERE order_id IS NULL;

COMMIT;

UPDATE VMS_VendorPerformance
SET order_id = 2
WHERE performance_id = 4;
-- Verify
SELECT * FROM VMS_VendorPerformance;


COMMIT;








-- =========================
-- JOIN TYPES DEMONSTRATION
-- =========================

-- INNER JOIN

SELECT 
    v.vendor_id,
    v.vendor_name,
    o.order_id,
    o.status
FROM VMS_Vendors v
INNER JOIN VMS_Orders o
ON v.vendor_id = o.vendor_id;

-- LEFT JOIN

SELECT 
    v.vendor_id,
    v.vendor_name,
    o.order_id,
    o.status
FROM VMS_Vendors v
LEFT JOIN VMS_Orders o
ON v.vendor_id = o.vendor_id;

--RIGHT JOIN

SELECT 
    v.vendor_id,
    v.vendor_name,
    o.order_id,
    o.status
FROM VMS_Vendors v
RIGHT JOIN VMS_Orders o
ON v.vendor_id = o.vendor_id;

--FULL OUTER JOIN

SELECT 
    v.vendor_id,
    v.vendor_name,
    o.order_id,
    o.status
FROM VMS_Vendors v
FULL OUTER JOIN VMS_Orders o
ON v.vendor_id = o.vendor_id;

--SELF JOIN
--Shows vendors in the same city <> !=

SELECT 
    v1.vendor_name AS vendor_1,
    v2.vendor_name AS vendor_2,
    v1.city
FROM VMS_Vendors v1
JOIN VMS_Vendors v2
ON v1.city = v2.city
AND v1.vendor_id <> v2.vendor_id; 

SELECT 
    v1.vendor_name AS vendor_1,
    v2.vendor_name AS vendor_2,
    v1.city
FROM VMS_Vendors v1
JOIN VMS_Vendors v2
ON v1.city = v2.city
AND v1.vendor_id < v2.vendor_id;

SELECT 
    v1.vendor_name AS vendor_1,
    v2.vendor_name AS vendor_2,
    v1.city
FROM VMS_Vendors v1
JOIN VMS_Vendors v2
ON v1.city = v2.city;
--CROSS JOIN

SELECT 
    v.vendor_name,
    o.order_id
FROM VMS_Vendors v
CROSS JOIN VMS_Orders o;





-- =========================
-- 6. Quick Verification Queries
-- =========================

SELECT * FROM VMS_Vendors;
SELECT * FROM VMS_Products;
SELECT * FROM VMS_Users_Employees;
SELECT * FROM VMS_Orders;
SELECT * FROM VMS_OrderDetails;
SELECT * FROM VMS_Payments;
SELECT * FROM VMS_Inventory;
SELECT * FROM VMS_VendorContracts;
SELECT * FROM VMS_VendorPerformance;


-- =========================
-- 4. Verification
-- =========================
SELECT 'Vendors'          AS table_name, COUNT(*) AS row_count FROM VMS_Vendors
UNION ALL
SELECT 'Products'         AS table_name, COUNT(*) FROM VMS_Products
UNION ALL
SELECT 'Users_Employees'  AS table_name, COUNT(*) FROM VMS_Users_Employees
UNION ALL
SELECT 'Orders'           AS table_name, COUNT(*) FROM VMS_Orders
UNION ALL
SELECT 'OrderDetails'     AS table_name, COUNT(*) FROM VMS_OrderDetails
UNION ALL
SELECT 'Payments'         AS table_name, COUNT(*) FROM VMS_Payments
UNION ALL
SELECT 'VendorContracts'  AS table_name, COUNT(*) FROM VMS_VendorContracts
UNION ALL
SELECT 'VendorPerformance'AS table_name, COUNT(*) FROM VMS_VendorPerformance
UNION ALL
SELECT 'Inventory'        AS table_name, COUNT(*) FROM VMS_Inventory
ORDER BY table_name;



-- Quick look at Contracts
SELECT contract_id, contract_number, vendor_id, status, document_path 
FROM VMS_VendorContracts;

-- Quick look at Performance
SELECT performance_id, vendor_id, order_id, rating, review_comments 
FROM VMS_VendorPerformance;





-- =============================================
-- VENDOR REVENUE SUMMARY VIEW
-- (Total money spent per vendor)
-- =============================================

CREATE OR REPLACE VIEW VMS_Vendor_Revenue AS
SELECT 
    v.vendor_id,
    v.vendor_name,
    v.city,
    COUNT(DISTINCT o.order_id)                    AS total_orders,
    SUM(od.quantity * p.price)                    AS total_revenue,           -- Total amount spent
    ROUND(AVG(od.quantity * p.price), 2)          AS average_order_value,
    MAX(o.order_date)                             AS last_order_date,
    COUNT(CASE WHEN o.status = 'Delivered' THEN 1 END) AS delivered_orders,
    COUNT(CASE WHEN o.status = 'Pending' THEN 1 END)   AS pending_orders
FROM VMS_Vendors v
LEFT JOIN VMS_Orders o ON v.vendor_id = o.vendor_id
LEFT JOIN VMS_OrderDetails od ON o.order_id = od.order_id
LEFT JOIN VMS_Products p ON od.product_id = p.product_id
GROUP BY v.vendor_id, v.vendor_name, v.city
ORDER BY total_revenue DESC;


-- Main Revenue Report (Most Important)
SELECT * FROM VMS_Vendor_Revenue;



-- Detailed Revenue by Vendor and Product
CREATE OR REPLACE VIEW VMS_Vendor_Revenue_Detail AS
SELECT 
    v.vendor_name,
    p.product_name,
    COUNT(DISTINCT o.order_id)           AS number_of_orders,
    SUM(od.quantity)                     AS total_quantity_purchased,
    SUM(od.quantity * p.price)           AS total_amount_spent,
    MIN(o.order_date)                    AS first_order_date,
    MAX(o.order_date)                    AS last_order_date
FROM VMS_Vendors v
JOIN VMS_Orders o ON v.vendor_id = o.vendor_id
JOIN VMS_OrderDetails od ON o.order_id = od.order_id
JOIN VMS_Products p ON od.product_id = p.product_id
GROUP BY v.vendor_name, p.product_name
ORDER BY total_amount_spent DESC;

SELECT * FROM VMS_Vendor_Revenue_Detail;


-- Simple Total Revenue per Vendor
SELECT 
    v.vendor_name,
    COUNT(DISTINCT o.order_id) AS total_orders,
    SUM(od.quantity * p.price) AS total_revenue
FROM VMS_Vendors v
LEFT JOIN VMS_Orders o ON v.vendor_id = o.vendor_id
LEFT JOIN VMS_OrderDetails od ON o.order_id = od.order_id
LEFT JOIN VMS_Products p ON od.product_id = p.product_id
GROUP BY v.vendor_name
ORDER BY total_revenue DESC;
-- =========================
-- 4. VIEW
-- =========================

CREATE VIEW VMS_Order_Summary AS
SELECT o.order_id, v.vendor_name, o.order_date, o.status
FROM VMS_Orders o
JOIN VMS_Vendors v ON o.vendor_id = v.vendor_id;




SELECT * FROM VMS_Order_Summary;

SELECT view_name
FROM user_views;

SELECT view_name, text
FROM user_views
WHERE view_name = 'VMS_ORDER_SUMMARY';


DROP VIEW VMS_Order_Summary;


-- ========================================
-- VIEWS FOR VMS (Vendor Management System)
-- ========================================

-- 1. Vendor Summary View (Most Useful)
CREATE OR REPLACE VIEW VMS_Vendor_Summary AS
SELECT 
    v.vendor_id,
    v.vendor_name,
    v.email,
    v.phone,
    v.city,
    v.status,
    COUNT(DISTINCT o.order_id)        AS total_orders,
    COUNT(DISTINCT c.contract_id)     AS total_contracts,
    AVG(p.rating)                     AS average_rating,
    SUM(CASE WHEN p.rating >= 4 THEN 1 ELSE 0 END) AS high_rated_reviews
FROM VMS_Vendors v
LEFT JOIN VMS_Orders o ON v.vendor_id = o.vendor_id
LEFT JOIN VMS_VendorContracts c ON v.vendor_id = c.vendor_id
LEFT JOIN VMS_VendorPerformance p ON v.vendor_id = p.vendor_id
GROUP BY v.vendor_id, v.vendor_name, v.email, v.phone, v.city, v.status;

-- 2. Order Details with Product & Vendor Info
CREATE OR REPLACE VIEW VMS_Order_Details_View AS
SELECT 
    o.order_id,
    o.order_date,
    o.status,
    o.placed_by_user_id,
    v.vendor_name,
    p.product_name,
    od.quantity,
    p.price,
    (od.quantity * p.price) AS subtotal
FROM VMS_Orders o
JOIN VMS_VendorProducts vp ON o.vendor_id = vp.vendor_id
JOIN VMS_Products p ON vp.product_id = p.product_id
JOIN VMS_OrderDetails od ON o.order_id = od.order_id 
                         AND od.product_id = p.product_id
JOIN VMS_Vendors v ON o.vendor_id = v.vendor_id;

-- 3. Low Stock Alert View
CREATE OR REPLACE VIEW VMS_Low_Stock_Alert AS
SELECT 
    i.inventory_id,
    p.product_name,
    p.category,
    i.quantity_in_stock,
    i.reorder_level,
    (i.reorder_level - i.quantity_in_stock) AS shortage_quantity
FROM VMS_Inventory i
JOIN VMS_Products p ON i.product_id = p.product_id
WHERE i.quantity_in_stock <= i.reorder_level;

-- 4. Vendor Contracts View
CREATE OR REPLACE VIEW VMS_Active_Contracts AS
SELECT 
    c.contract_id,
    c.contract_number,
    v.vendor_name,
    c.start_date,
    c.end_date,
    c.payment_terms,
    c.discount_percentage,
    c.status,
    c.document_path
FROM VMS_VendorContracts c
JOIN VMS_Vendors v ON c.vendor_id = v.vendor_id
WHERE c.status = 'Active';

-- 5. Vendor Performance Summary
CREATE OR REPLACE VIEW VMS_Vendor_Performance_View AS
SELECT 
    v.vendor_name,
    COUNT(p.performance_id) AS total_reviews,
    ROUND(AVG(p.rating), 2) AS avg_rating,
    MIN(p.rating) AS lowest_rating,
    MAX(p.rating) AS highest_rating,
    COUNT(CASE WHEN p.order_id IS NOT NULL THEN 1 END) AS order_linked_reviews
FROM VMS_Vendors v
LEFT JOIN VMS_VendorPerformance p ON v.vendor_id = p.vendor_id
GROUP BY v.vendor_name;

-- 6. Full Order Summary (Very Useful)
CREATE OR REPLACE VIEW VMS_Order_Summary AS
SELECT 
    o.order_id,
    o.order_date,
    v.vendor_name,
    u.full_name AS placed_by,
    o.status,
    COUNT(od.order_detail_id) AS total_items,
    SUM(od.quantity) AS total_quantity,
    SUM(od.quantity * p.price) AS estimated_total_amount
FROM VMS_Orders o
JOIN VMS_Vendors v ON o.vendor_id = v.vendor_id
LEFT JOIN VMS_Users_Employees u ON o.placed_by_user_id = u.user_id
LEFT JOIN VMS_OrderDetails od ON o.order_id = od.order_id
LEFT JOIN VMS_Products p ON od.product_id = p.product_id
GROUP BY o.order_id, o.order_date, v.vendor_name, u.full_name, o.status;

COMMIT;

SELECT * FROM VMS_Vendor_Summary;
SELECT * FROM VMS_Low_Stock_Alert;
SELECT * FROM VMS_Active_Contracts;
SELECT * FROM VMS_Order_Summary;
SELECT * FROM VMS_Vendor_Performance_View;

-- =========================
-- 5. FUNCTION
-- =========================

CREATE OR REPLACE FUNCTION VMS_Get_Order_Total(p_order_id NUMBER)
RETURN NUMBER
IS
    total NUMBER;
BEGIN
    SELECT SUM(p.price * od.quantity)
    INTO total
    FROM VMS_OrderDetails od
    JOIN VMS_Products p ON od.product_id = p.product_id
    WHERE od.order_id = p_order_id;

    RETURN NVL(total,0);
END;
/





-- Create a function that returns the total revenue for a vendor
CREATE OR REPLACE FUNCTION get_vendor_revenue(p_vendor_id IN NUMBER)
RETURN NUMBER
IS
    v_total NUMBER;
BEGIN
    SELECT SUM(od.quantity * vp.supply_price)
    INTO v_total
    FROM VMS_Orders o
    JOIN VMS_OrderDetails od ON o.order_id = od.order_id
    JOIN VMS_VendorProducts vp ON o.vendor_id = vp.vendor_id 
                              AND od.product_id = vp.product_id
    WHERE o.vendor_id = p_vendor_id;

    RETURN NVL(v_total, 0);   -- Return 0 if no orders
END;
/


-- Use in a SQL query
SELECT 
    vendor_name,
    get_vendor_revenue(vendor_id) AS total_spent
FROM VMS_Vendors;

-- Or call it directly of vendor 1
SELECT get_vendor_revenue(1) FROM DUAL;

-- Or call it directly of vendor 2
SELECT get_vendor_revenue(2) FROM DUAL;


-- List all functions created by you (current user)
SELECT 
    object_name AS function_name,
    status,           -- VALID or INVALID
    created,
    last_ddl_time
FROM USER_OBJECTS 
WHERE OBJECT_TYPE = 'FUNCTION'
ORDER BY object_name;

-- Count total number of functions
SELECT COUNT(*) AS total_functions
FROM USER_OBJECTS 
WHERE OBJECT_TYPE = 'FUNCTION';

--Best All-in-One Query (Recommended)
SELECT 
    object_name AS function_name,
    status,
    TO_CHAR(created, 'DD-MON-YYYY HH24:MI') AS created_on,
    TO_CHAR(last_ddl_time, 'DD-MON-YYYY HH24:MI') AS last_modified
FROM USER_OBJECTS 
WHERE OBJECT_TYPE = 'FUNCTION'
ORDER BY object_name;

--If You Want to See Functions from Other Schemas Too
SELECT 
    owner,
    object_name AS function_name,
    status
FROM ALL_OBJECTS 
WHERE OBJECT_TYPE = 'FUNCTION'
ORDER BY owner, object_name;

-- =============================================
-- USEFUL FUNCTIONS FOR VMS
-- =============================================

-- 1. Get Total Quantity Purchased from a Vendor
CREATE OR REPLACE FUNCTION VMS_Get_Vendor_Total_Quantity(p_vendor_id IN NUMBER)
RETURN NUMBER
IS
    v_total_qty NUMBER;
BEGIN
    SELECT SUM(od.quantity)
    INTO v_total_qty
    FROM VMS_Orders o
    JOIN VMS_OrderDetails od ON o.order_id = od.order_id
    WHERE o.vendor_id = p_vendor_id;

    RETURN NVL(v_total_qty, 0);
END;
/

SELECT VMS_Get_Vendor_Total_Quantity(1) FROM DUAL;     -- Total quantity bought from vendor 1

-- 2. Get Average Rating of a Vendor
CREATE OR REPLACE FUNCTION VMS_Get_Vendor_Avg_Rating(p_vendor_id IN NUMBER)
RETURN NUMBER
IS
    v_avg_rating NUMBER;
BEGIN
    SELECT ROUND(AVG(rating), 2)
    INTO v_avg_rating
    FROM VMS_VendorPerformance
    WHERE vendor_id = p_vendor_id;

    RETURN NVL(v_avg_rating, 0);
END;
/

SELECT VMS_Get_Vendor_Avg_Rating(1) FROM DUAL;         -- Average rating of vendor 1

SELECT VMS_Get_Vendor_Avg_Rating(2) FROM DUAL; 

SELECT VMS_Get_Vendor_Avg_Rating(3) FROM DUAL; 

SELECT VMS_Get_Vendor_Avg_Rating(4) FROM DUAL; 

-- 3. Check Low Stock for a Product
CREATE OR REPLACE FUNCTION VMS_Is_Low_Stock(p_product_id IN NUMBER)
RETURN VARCHAR2
IS
    v_qty NUMBER;
    v_reorder NUMBER;
BEGIN
    SELECT quantity_in_stock, reorder_level
    INTO v_qty, v_reorder
    FROM VMS_Inventory
    WHERE product_id = p_product_id;

    IF v_qty <= v_reorder THEN
        RETURN 'YES - Low Stock';
    ELSE
        RETURN 'OK';
    END IF;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RETURN 'Product Not Found';
END;
/

SELECT p.product_name, VMS_Is_Low_Stock(p.product_id) 
FROM VMS_Products p;

-- 4. Get Number of Active Contracts for a Vendor
CREATE OR REPLACE FUNCTION VMS_Get_Active_Contracts(p_vendor_id IN NUMBER)
RETURN NUMBER
IS
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM VMS_VendorContracts
    WHERE vendor_id = p_vendor_id 
      AND status = 'Active';

    RETURN NVL(v_count, 0);
END;
/

SELECT 
    vendor_name,
    VMS_Get_Active_Contracts(vendor_id) AS active_contracts,
    VMS_Get_Vendor_Avg_Rating(vendor_id) AS avg_rating
FROM VMS_Vendors;


-- pay now button 
CREATE OR REPLACE FUNCTION VMS_Get_Order_Total(p_order_id IN NUMBER) 
RETURN NUMBER IS
    v_total NUMBER := 0;
BEGIN
    SELECT NVL(SUM(p.PRICE * d.QUANTITY), 0)
    INTO v_total
    FROM VMS_ORDERDETAILS d
    JOIN VMS_PRODUCTS p ON d.PRODUCT_ID = p.PRODUCT_ID
    WHERE d.ORDER_ID = p_order_id;
    
    RETURN v_total;
EXCEPTION
    WHEN OTHERS THEN RETURN 0;
END;
/

SELECT OBJECT_NAME, STATUS
FROM USER_OBJECTS
WHERE OBJECT_TYPE = 'FUNCTION'
AND OBJECT_NAME = 'VMS_GET_ORDER_TOTAL';

SELECT ORDER_ID, VMS_Get_Order_Total(ORDER_ID) AS TOTAL
FROM VMS_ORDERS;

-- 6. Calculate Discount Amount for a Contract
CREATE OR REPLACE FUNCTION VMS_Calculate_Discount(p_contract_id IN NUMBER)
RETURN NUMBER
IS
    v_discount NUMBER;
BEGIN
    SELECT contract_value * (discount_percentage / 100)
    INTO v_discount
    FROM VMS_VendorContracts
    WHERE contract_id = p_contract_id;

    RETURN NVL(v_discount, 0);
END;
/

SELECT 
    contract_id, 
    contract_value, 
    VMS_Calculate_Discount(contract_id) AS calculated_discount
FROM VMS_VendorContracts;

SELECT *
FROM VMS_VendorContracts
WHERE VMS_Calculate_Discount(contract_id) > 500;

SELECT VMS_Calculate_Discount(1) FROM DUAL;   -- Discount for contract id 1
SELECT VMS_Calculate_Discount(2) FROM DUAL; 
SELECT VMS_Calculate_Discount(3) FROM DUAL; 
SELECT VMS_Calculate_Discount(4) FROM DUAL; 
-- =========================
-- 6. PROCEDURE
-- =========================

CREATE OR REPLACE PROCEDURE VMS_Add_Vendor(
    p_name  IN VARCHAR2,
    p_email IN VARCHAR2,
    p_phone IN VARCHAR2,
    p_city  IN VARCHAR2
)
IS
BEGIN
    INSERT INTO VMS_Vendors (
        vendor_id, 
        vendor_name, 
        email, 
        phone, 
        city, 
        status
    )
    VALUES (
        vms_vendor_seq.NEXTVAL, 
        p_name, 
        p_email, 
        p_phone, 
        p_city, 
        'Active' -- Set as 'Active' by default on creation
    );

    COMMIT;
END;
/

BEGIN
    VMS_Add_Vendor('Tech Solutions', 'contact@techsol.com', '555-0102', 'Chicago');
END;
/


select * from VMS_Vendors;


-- =========================
-- 7. TRIGGER
-- =========================

--Whenever a new payment is added, if the user does NOT provide a payment date, the database automatically sets it to today’s date.

CREATE OR REPLACE TRIGGER VMS_Payment_Trigger
BEFORE INSERT ON VMS_Payments
FOR EACH ROW
BEGIN
    IF :NEW.payment_date IS NULL THEN
        :NEW.payment_date := SYSDATE;
    END IF;
END;
/

--Simple Trigger: Auto Set Order Date
--
--👉 Purpose:
--If user forgets to enter order_date, the database will automatically set it.

CREATE OR REPLACE TRIGGER trg_set_order_date
BEFORE INSERT ON VMS_Orders
FOR EACH ROW
BEGIN
    IF :NEW.order_date IS NULL THEN
        :NEW.order_date := SYSDATE;
    END IF;
END;
/

INSERT INTO VMS_Orders (order_id, vendor_id, placed_by_user_id)
VALUES (VMS_ORDER_SEQ.NEXTVAL, 1, 1);

SELECT * FROM VMS_Orders;

--1. Inventory Auto-Update (MOST IMPORTANT)
--
--👉 When an order is placed → stock should decrease

--CREATE OR REPLACE TRIGGER trg_update_inventory
--AFTER INSERT ON VMS_OrderDetails
--FOR EACH ROW
--BEGIN
--    UPDATE VMS_Inventory
--    SET quantity_in_stock = quantity_in_stock - :NEW.quantity,
--        last_updated = SYSDATE
--    WHERE product_id = :NEW.product_id;
--
--    IF SQL%ROWCOUNT = 0 THEN
--        RAISE_APPLICATION_ERROR(-20003, 'Product not found in inventory');
--    END IF;
--END;
--/

--2. Prevent Negative Stock (CRITICAL)
--
--👉 Stop order if stock is not enough
--
--CREATE OR REPLACE TRIGGER trg_check_stock
--BEFORE INSERT ON VMS_OrderDetails
--FOR EACH ROW
--DECLARE
--    v_stock NUMBER;
--BEGIN
--    SELECT quantity_in_stock
--    INTO v_stock
--    FROM VMS_Inventory
--    WHERE product_id = :NEW.product_id;
--
--    IF v_stock < :NEW.quantity THEN
--        RAISE_APPLICATION_ERROR(-20001, 'Not enough stock available');
--    END IF;
--
--EXCEPTION
--    WHEN NO_DATA_FOUND THEN
--        RAISE_APPLICATION_ERROR(-20004, 'Product not found in inventory');
--END;
--/

--3.Auto Mark Order as Delivered
--
--👉 When FULL payment is done → status = Delivered
--This automatically marks an order as 'Delivered' once you pay for it.
CREATE OR REPLACE TRIGGER trg_update_order_status
AFTER INSERT ON VMS_Payments
FOR EACH ROW
DECLARE
    v_total NUMBER;
    v_paid  NUMBER;
BEGIN
    -- Get total order amount
    v_total := VMS_Get_Order_Total(:NEW.order_id);

    -- Get total paid amount
    SELECT NVL(SUM(amount), 0)
    INTO v_paid
    FROM VMS_Payments
    WHERE order_id = :NEW.order_id;

    -- Update only if fully paid AND not already delivered
    IF v_paid >= v_total THEN
        UPDATE VMS_Orders
        SET status = 'Delivered'
        WHERE order_id = :NEW.order_id
        AND status <> 'Delivered';
    END IF;
END;
/

--5. Auto Vendor Status Update (SMART FEATURE)
--
--👉 Expire vendors automatically based on contract

CREATE OR REPLACE TRIGGER trg_update_vendor_status
AFTER UPDATE OF status ON VMS_VendorContracts
FOR EACH ROW
BEGIN
    IF :NEW.status = 'Expired' THEN
        UPDATE VMS_Vendors
        SET status = 'Inactive'
        WHERE vendor_id = :NEW.vendor_id;
    END IF;
END;
/

--Inventory Not Auto-Updating 
--CREATE OR REPLACE TRIGGER trg_update_inventory
--AFTER INSERT ON VMS_OrderDetails
--FOR EACH ROW
--BEGIN
--    UPDATE VMS_Inventory
--    SET quantity_in_stock = quantity_in_stock - :NEW.quantity
--    WHERE product_id = :NEW.product_id;
--END;
--/

-- This trigger will watch for the status changing to 'Delivered'
-- and then add the quantities to your inventory.

CREATE OR REPLACE TRIGGER trg_increase_stock_on_delivery
AFTER UPDATE OF status ON VMS_Orders
FOR EACH ROW
WHEN (NEW.status = 'Delivered' AND (OLD.status IS NULL OR OLD.status <> 'Delivered'))
BEGIN
    -- Loop through all products in this specific order
    FOR item IN (SELECT product_id, quantity FROM VMS_OrderDetails WHERE order_id = :NEW.order_id) LOOP
        
        -- 1. Try to add to existing inventory
        UPDATE VMS_Inventory
        SET quantity_in_stock = quantity_in_stock + item.quantity,
            last_updated = SYSDATE
        WHERE product_id = item.product_id;
        
        -- 2. If the product isn't in the inventory table yet, insert it
        IF SQL%ROWCOUNT = 0 THEN
            INSERT INTO VMS_Inventory (inventory_id, product_id, quantity_in_stock, last_updated)
            VALUES (VMS_INVENTORY_SEQ.NEXTVAL, item.product_id, item.quantity, SYSDATE);
        END IF;
        
    END LOOP;
END;
/


-- =========================
-- 8. TEST QUERIES
-- =========================

SELECT * FROM VMS_Vendors;
SELECT * FROM VMS_Products;
SELECT * FROM VMS_Order_Summary;

SELECT VMS_Get_Order_Total(1) FROM dual;

BEGIN
    VMS_Add_Vendor('New Vendor','new@gmail.com','03000000000','Islamabad');
END;
/






--======================================


-- =========================================
-- USER & SESSION CHECK
-- =========================================
SHOW USER;
SHOW CON_NAME;

SELECT username FROM all_users WHERE username = 'VMS_USER';

-- =========================================
-- BASIC TABLE CHECKS
-- =========================================
SELECT * FROM VMS_Vendors;
SELECT * FROM VMS_Products;
SELECT * FROM VMS_VendorProducts;
SELECT * FROM VMS_Orders;
SELECT * FROM VMS_OrderDetails;
SELECT * FROM VMS_Payments;

-- =========================================
-- DESCRIBE TABLES
-- =========================================
DESC VMS_Vendors;
DESC VMS_Products;
DESC VMS_VendorProducts;
DESC VMS_Orders;
DESC VMS_OrderDetails;
DESC VMS_Payments;

-- =========================================
-- ORDERED & FILTERED DATA
-- =========================================
SELECT vendor_id, vendor_name, city, email 
FROM VMS_Vendors 
ORDER BY vendor_id;

SELECT * FROM VMS_Vendors 
ORDER BY vendor_id DESC FETCH FIRST 10 ROWS ONLY;

SELECT COUNT(*) FROM VMS_Vendors;

-- =========================================
-- DELETE / UPDATE
-- =========================================
DELETE FROM VMS_Vendors WHERE vendor_id = 5;

UPDATE VMS_Orders 
SET status = 'Delivered' 
WHERE order_id = 1;

DELETE FROM VMS_Orders 
WHERE status = 'Cancelled';

-- =========================================
-- CONSTRAINT CHECKS
-- =========================================
SELECT constraint_name, constraint_type, table_name
FROM user_constraints
WHERE table_name LIKE 'VMS_%';

SELECT table_name, column_name, nullable
FROM user_tab_columns
WHERE table_name LIKE 'VMS_%';

-- =========================================
-- RELATIONSHIP CHECK (FOREIGN KEYS)
-- =========================================
SELECT constraint_name, table_name
FROM user_constraints
WHERE constraint_type = 'R';

-- =========================================
-- JOIN QUERIES (VERY IMPORTANT)
-- =========================================

-- Orders with Vendor
SELECT o.order_id, v.vendor_name, o.order_date
FROM VMS_Orders o
JOIN VMS_Vendors v ON o.vendor_id = v.vendor_id;

-- Order Details with Product
SELECT o.order_id, p.product_name, od.quantity
FROM VMS_OrderDetails od
JOIN VMS_Products p ON od.product_id = p.product_id
JOIN VMS_Orders o ON od.order_id = o.order_id;

-- Products by Vendor
SELECT p.product_name, vp.supply_price
FROM VMS_Products p
JOIN VMS_VendorProducts vp ON p.product_id = vp.product_id
WHERE vp.vendor_id = 1;

-- =========================================
-- FUNCTION USAGE
-- =========================================
SELECT order_id, VMS_Get_Order_Total(order_id) AS total_amount
FROM VMS_Orders;

SELECT VMS_Get_Order_Total(1) FROM dual;

-- =========================================
-- VIEW USAGE
-- =========================================
SELECT * FROM VMS_Order_Summary;

SELECT * FROM VMS_Order_Summary 
WHERE status = 'Pending';

-- =========================================
-- ADVANCED REPORT (HIGH MARKS QUERY 🔥)
-- =========================================
SELECT 
    v.vendor_name,
    o.order_id,
    o.status,
    SUM(od.quantity * p.price) AS order_total,
    COUNT(DISTINCT od.product_id) AS total_products,
    NVL(SUM(pay.amount), 0) AS paid_amount
FROM VMS_Vendors v
JOIN VMS_Orders o ON v.vendor_id = o.vendor_id
JOIN VMS_OrderDetails od ON o.order_id = od.order_id
JOIN VMS_Products p ON od.product_id = p.product_id
LEFT JOIN VMS_Payments pay ON o.order_id = pay.order_id
GROUP BY v.vendor_name, o.order_id, o.status
ORDER BY o.order_id;

-- =========================================
-- ANALYSIS QUERIES (FOR VIVA)
-- =========================================

-- Vendors in Lahore
SELECT * FROM VMS_Vendors WHERE city = 'Lahore';

-- Pending Orders
SELECT * FROM VMS_Orders WHERE status = 'Pending';

-- Expensive Products
SELECT product_name, price 
FROM VMS_Products 
WHERE price > 50000;

-- Orders with Payment Info
SELECT o.order_id, v.vendor_name, p.amount, p.payment_date
FROM VMS_Orders o
JOIN VMS_Vendors v ON o.vendor_id = v.vendor_id
LEFT JOIN VMS_Payments p ON o.order_id = p.order_id;

-- Count orders per vendor
SELECT v.vendor_name, COUNT(o.order_id) AS order_count
FROM VMS_Vendors v
LEFT JOIN VMS_Orders o ON v.vendor_id = o.vendor_id
GROUP BY v.vendor_name;

-- =========================================
-- PROCEDURE TEST
-- =========================================
BEGIN
    VMS_Add_Vendor('Test Vendor','test@gmail.com','03000000000','Islamabad');
END;
/

SELECT * FROM VMS_Vendors;

-- =========================================
-- TRIGGER TEST
-- =========================================
INSERT INTO VMS_Payments (payment_id, order_id, amount)
VALUES (999, 1, 5000);

SELECT * FROM VMS_Payments;

-- =========================================
-- CLEANUP (OPTIONAL)
-- =========================================
-- DROP VIEW VMS_Order_Summary;
-- DROP TABLE VMS_Payments CASCADE CONSTRAINTS;
-- DROP TABLE VMS_OrderDetails CASCADE CONSTRAINTS;
-- DROP TABLE VMS_Orders CASCADE CONSTRAINTS;
-- DROP TABLE VMS_VendorProducts CASCADE CONSTRAINTS;
-- DROP TABLE VMS_Products CASCADE CONSTRAINTS;
-- DROP TABLE VMS_Vendors CASCADE CONSTRAINTS;


=========================
-- 1. DROP TABLES (Optional - Use if you want to start completely fresh)
-- =========================
 DROP TABLE VMS_Inventory CASCADE CONSTRAINTS;
 DROP TABLE VMS_VendorPerformance CASCADE CONSTRAINTS;
 DROP TABLE VMS_VendorContracts CASCADE CONSTRAINTS;
 DROP TABLE VMS_Payments CASCADE CONSTRAINTS;
 DROP TABLE VMS_OrderDetails CASCADE CONSTRAINTS;
 DROP TABLE VMS_Orders CASCADE CONSTRAINTS;
 DROP TABLE VMS_VendorProducts CASCADE CONSTRAINTS;
 DROP TABLE VMS_Products CASCADE CONSTRAINTS;
 DROP TABLE VMS_Vendors CASCADE CONSTRAINTS;
 DROP TABLE VMS_Users_Employees CASCADE CONSTRAINTS;



-- Check all sequences in your schema
SELECT 
    sequence_name,
    min_value,
    max_value,
    increment_by,
    last_number,           -- This shows the next value that will be used
    cycle_flag,
    order_flag
FROM 
    USER_SEQUENCES
ORDER BY 
    sequence_name;
    
SELECT sequence_name 
FROM USER_SEQUENCES 
ORDER BY sequence_name;

SELECT 
    sequence_name, 
    last_number,
    increment_by 
FROM USER_SEQUENCES 
WHERE sequence_name = 'VMS_INVENTORY_SEQ';


SELECT LAST_NUMBER 
FROM USER_SEQUENCES 
WHERE SEQUENCE_NAME = 'VMS_VENDOR_SEQ';

=========================
-- 2. DROP SEQUENCES (To reset them)
-- =========================
DROP SEQUENCE VMS_VENDOR_SEQ;
DROP SEQUENCE VMS_PRODUCT_SEQ;
DROP SEQUENCE VMS_ORDER_SEQ;
DROP SEQUENCE VMS_ORDER_DETAIL_SEQ;
DROP SEQUENCE VMS_PAYMENT_SEQ;
DROP SEQUENCE VMS_USER_SEQ;
DROP SEQUENCE VMS_CONTRACT_SEQ;
DROP SEQUENCE VMS_PERFORMANCE_SEQ;
DROP SEQUENCE VMS_INVENTORY_SEQ;
