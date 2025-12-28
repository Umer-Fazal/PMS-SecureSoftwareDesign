CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'patient') NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE Patient
ADD patient_password_hash VARCHAR(255) NOT NULL AFTER patient_contact;

-------------------------------------------------------------------------------------------------------------------

-- Create Table with Auto-Increment for manufacturer_id
CREATE TABLE manufacturer (
    manufacturer_id INT PRIMARY KEY,
    manufacturer_name VARCHAR2(100) NOT NULL,
    manufacturer_address VARCHAR2(100) NOT NULL,
    manufacturer_phone VARCHAR2(20) NOT NULL,
    manufacturer_email VARCHAR2(100) NOT NULL
);

-- Insert Manufacturer 1
INSERT INTO manufacturer (manufacturer_name, manufacturer_address, manufacturer_phone, manufacturer_email)
VALUES ('Ali', 'Gulshan-e-Iqbal', '021-1234212', 'Ali@gmail.com');

-- Insert Manufacturer 2
INSERT INTO manufacturer (manufacturer_name, manufacturer_address, manufacturer_phone, manufacturer_email)
VALUES ('Ahmed', 'Gulshan-e-Iqbal', '021-1234202', 'Ahmed@gmail.com');

-- Insert Manufacturer 3
INSERT INTO manufacturer (manufacturer_name, manufacturer_address, manufacturer_phone, manufacturer_email)
VALUES ('Haris', 'Gulshan-e-Iqbal', '021-1234202', 'Haris@gmail.com');

-- List Manufacturers
SELECT manufacturer_id, manufacturer_name, manufacturer_address, manufacturer_phone, manufacturer_email
FROM manufacturer;

-- Update Manufacturer with ID 101
UPDATE manufacturer
SET manufacturer_name = 'SAAD'
WHERE manufacturer_id = 101;

-- Update Manufacturer Address for ID 101
UPDATE manufacturer
SET manufacturer_address = 'Defence'
WHERE manufacturer_id = 101;

-- Update Manufacturer Phone for ID 101
UPDATE manufacturer
SET manufacturer_phone = '36363633'
WHERE manufacturer_id = 101;

-- Update Manufacturer Email for ID 101
UPDATE manufacturer
SET manufacturer_email = 'saad@yahoo.com'
WHERE manufacturer_id = 101;

-- Final List of Manufacturers
SELECT manufacturer_id, manufacturer_name, manufacturer_address, manufacturer_phone, manufacturer_email
FROM manufacturer;

-- Delete Manufacturer with ID 100 (it has already been deleted earlier)
DELETE FROM manufacturer
WHERE manufacturer_id = 100;

-- List Manufacturers after Deletion
SELECT manufacturer_id, manufacturer_name, manufacturer_address, manufacturer_phone, manufacturer_email
FROM manufacturer;
---------------------------------------------------------------------------------------------------------------------
-- Step 1: Create the Doctor Table
CREATE TABLE Doctor (
    doctor_id INT PRIMARY KEY,
    doctor_name VARCHAR2(100) NOT NULL,
    doctor_address VARCHAR2(100) NOT NULL,
    doctor_phone VARCHAR2(11) NOT NULL UNIQUE,
    doctor_email VARCHAR2(100) NOT NULL,
    doctor_DOB DATE NOT NULL,
    doctor_DOJ DATE NOT NULL
);

-------------------------trigger----------------------------
DELIMITER //

CREATE TRIGGER check_duplicate_doctor
BEFORE INSERT ON doctor
FOR EACH ROW
BEGIN
    DECLARE duplicate INT;

    -- Check if there is a doctor with the same name, phone number, or specialty
    SELECT COUNT(*) INTO duplicate
    FROM doctor
    WHERE doctor_name = NEW.doctor_name
    OR doctor_phone = NEW.doctor_phone;

    -- If a duplicate is found, raise an error
    IF duplicate > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error: Doctor with the same name, phone number, or specialty already exists.';
    END IF;
END //

DELIMITER ;

-------------------------------------------------
-- Insert first record for Doctor
INSERT INTO Doctor (doctor_name, doctor_address, doctor_phone, doctor_email, doctor_DOB, doctor_DOJ)
VALUES 
    ('Ali', 'Gulshan-e-Iqbal', '0244534212', 'Ali@gmail.com', TO_DATE('2002-12-09', 'YYYY-MM-DD'), TO_DATE('2022-01-12', 'YYYY-MM-DD'));

-- Insert second record for Doctor
INSERT INTO Doctor (doctor_name, doctor_address, doctor_phone, doctor_email, doctor_DOB, doctor_DOJ)
VALUES 
    ('Ahmed', 'Gulshan-e-maymar', '021123444', 'Ahmed@gmail.com', TO_DATE('2001-12-09', 'YYYY-MM-DD'), TO_DATE('2023-01-12', 'YYYY-MM-DD'));


-- Step 5: List all doctors
SELECT doctor_id, doctor_name, doctor_address, doctor_phone, doctor_email, doctor_DOB, doctor_DOJ
FROM Doctor;

-- Step 6: Update a doctor's information
UPDATE Doctor
SET doctor_name = 'Saad', 
    doctor_address = 'Gulshan-e-Iqbal', 
    doctor_phone = '0211234212',
    doctor_email = 'Ali@gmail.com', 
    doctor_DOB = TO_DATE('2002-12-09', 'YYYY-MM-DD'), 
    doctor_DOJ = TO_DATE('2022-01-12', 'YYYY-MM-DD')
WHERE doctor_id = 1;

-- Step 7: Delete a doctor
DELETE FROM Doctor WHERE doctor_id = 2;

-- Step 8: Load a specific doctor
SELECT doctor_name, doctor_address, doctor_phone, doctor_email, doctor_DOB, doctor_DOJ
FROM Doctor WHERE doctor_id = 1;

-- Step 9: Update a doctor's name
UPDATE Doctor
SET doctor_name = 'Ahmed'
WHERE doctor_id = 1;

-- Step 10: List all doctors (after updates and deletion)
SELECT doctor_id, doctor_name, doctor_address, doctor_phone, doctor_email, doctor_DOB, doctor_DOJ
FROM Doctor;

-- Step 11: Insert a new doctor record
INSERT INTO Doctor (doctor_name, doctor_address, doctor_phone, doctor_email, doctor_DOB, doctor_DOJ)
VALUES 
    ('Ali', 'Gulshan-e-Iqbal', '0244534212', 'Ali@gmail.com', TO_DATE('2002-12-09', 'YYYY-MM-DD'), TO_DATE('2022-01-12', 'YYYY-MM-DD'));
--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------


-- Create the table
CREATE TABLE medicine4 (
    medicine_id NUMBER PRIMARY KEY,  -- Use NUMBER instead of INT
    Medicine_name VARCHAR2(100) NOT NULL,  -- Use VARCHAR2 for strings
    medicine_price NUMBER NOT NULL,  -- Use NUMBER instead of INT
    medicine_MFG DATE NOT NULL,  -- Use DATE for date values
    medicine_Expiry DATE NOT NULL,  -- Use DATE for date values
    potency VARCHAR2(20),  -- VARCHAR2 for string
    manufacturer_id NUMBER NOT NULL,  -- Use NUMBER instead of INT
    is_expired CHAR(1) DEFAULT 'N'  -- Use CHAR(1) for boolean-like values (N for No, Y for Yes)
);


-- Insert Medicine into Medicine4 (No need to manually specify the medicine_id since the trigger will auto-generate it)
INSERT INTO medicine4 (Medicine_name, medicine_price, medicine_MFG, medicine_Expiry, potency, manufacturer_id)
SELECT 'zolp', 300, TO_DATE('2019/12/20', 'YYYY/MM/DD'), TO_DATE('2023/06/12', 'YYYY/MM/DD'), '10mg', manufacturer_id
FROM manufacturer
WHERE manufacturer_name = 'Haris';


INSERT INTO medicine4 (Medicine_name, medicine_price, medicine_MFG, medicine_Expiry, potency, manufacturer_id)
SELECT 'bluedol', 100, TO_DATE('2019/12/20', 'YYYY/MM/DD'), TO_DATE('2023/06/12', 'YYYY/MM/DD'), '10mg', manufacturer_id
FROM manufacturer
WHERE manufacturer_name = 'SAAD';

-- Update Medicine details in medicine4 table
UPDATE medicine4
SET Medicine_name = 'brufen',
    medicine_price = 200,
    medicine_MFG = TO_DATE('2020/12/12', 'YYYY/MM/DD'),
    medicine_Expiry = TO_DATE('2023/06/20', 'YYYY/MM/DD'),
    manufacturer_id = (SELECT manufacturer_id FROM manufacturer WHERE manufacturer_name = 'Ali')
WHERE medicine_id = 1 AND potency = '10mg';

-- Update Medicine Name
UPDATE medicine4
SET Medicine_name = 'panadol'
WHERE medicine_id = 1 AND potency = '10mg';

-- Update Medicine Manufacturing Date (MFG)
UPDATE medicine4
SET medicine_MFG = TO_DATE('2022/12/13', 'YYYY/MM/DD')
WHERE medicine_id = 1 AND potency = '10mg';

-- Update Medicine Expiry Date
UPDATE medicine4
SET medicine_Expiry = TO_DATE('2023/12/31', 'YYYY/MM/DD')
WHERE medicine_id = 1 AND potency = '10mg';

-- Update Medicine Price
UPDATE medicine4
SET medicine_price = 1000
WHERE medicine_id = 1 AND potency = '10mg';

-- Update Manufacturer Name
UPDATE medicine4
SET manufacturer_id = (SELECT manufacturer_id FROM manufacturer WHERE manufacturer_name = 'Ahmed')
WHERE medicine_id = 1 AND potency = '10mg';

DELETE FROM medicine4 
WHERE medicine_id IN (3, 5, 6);

-- Select the data to verify updates
SELECT * FROM medicine4;
--------------------------------------------------------------------------------------------------------------------------------------
-- Step 1: Create Patient Table
CREATE TABLE Patient (
    patient_id NUMBER PRIMARY KEY,  -- Use NUMBER data type for patient_id
    patient_name VARCHAR2(100) NOT NULL,
    patient_gender VARCHAR2(100) NOT NULL,
    patient_DOB DATE NOT NULL,
    patient_email VARCHAR2(100) NOT NULL,
    patient_address VARCHAR2(100) NOT NULL,
    patient_contact VARCHAR2(11) NOT NULL UNIQUE  -- Ensure contact is unique
);
---------------------------------trigger----------------------------------------------
DELIMITER //

CREATE TRIGGER check_duplicate_patient
BEFORE INSERT ON Patient
FOR EACH ROW
BEGIN
    DECLARE duplicate INT;

    -- Check if there is a patient with the same name or phone number
    SELECT COUNT(*) INTO duplicate
    FROM Patient
    WHERE patient_name = NEW.patient_name
    OR patient_contact = NEW.patient_contact;

    -- If a duplicate is found, raise an error
    IF duplicate > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error: Patient with the same name or phone number already exists.';
    END IF;
END //

DELIMITER ;
-- Step 4: Insert records into Patient table
INSERT INTO Patient (patient_name, patient_gender, patient_DOB, patient_email, patient_address, patient_contact)
VALUES ('Ali Raza', 'male', TO_DATE('2002-12-27', 'YYYY-MM-DD'), 'Ali@gmail.com', 'Bisma Town', '03332164492');

INSERT INTO Patient (patient_name, patient_gender, patient_DOB, patient_email, patient_address, patient_contact)
VALUES ('Ahmed', 'male', TO_DATE('2002-11-20', 'YYYY-MM-DD'), 'Ahmed@gmail.com', 'Muka Chowk', '03316734986');

-- Step 5: Select all records from Patient table
SELECT * FROM Patient;

-- Step 6: Update a patient record
UPDATE Patient
SET patient_name = 'Khusama', patient_gender = 'male', patient_DOB = TO_DATE('2002-12-27', 'YYYY-MM-DD'),
    patient_email = 'khusama@gmail.com', patient_address = 'Bisma Town', patient_contact = '03313198649'
WHERE patient_id = 1;

-- Step 7: Select all records from Patient table after update
SELECT * FROM Patient;

-- Step 8: Delete a patient record
DELETE FROM Patient WHERE patient_id = 2;

-- Step 9: Select all records after deletion
SELECT * FROM Patient;

-- Step 10: Load specific record by ID
SELECT * FROM Patient WHERE patient_id = 1;

-- Step 11: Update specific patient data (Name)
UPDATE Patient SET patient_name = 'Khusama Khan' WHERE patient_id = 1;

-- Step 12: Select all records after name update
SELECT * FROM Patient;

-- Step 13: Update patient address
UPDATE Patient SET patient_address = 'Defence' WHERE patient_id = 1;

-- Step 14: Select all records after address update
SELECT * FROM Patient;

-- Step 15: Update patient contact
UPDATE Patient SET patient_contact = '36363633' WHERE patient_id = 1;

-- Step 16: Select all records after contact update
SELECT * FROM Patient;

-- Step 17: Update patient email
UPDATE Patient SET patient_email = 'khusamakhan@yahoo.com' WHERE patient_id = 1;

-- Step 18: Select all records after email update
SELECT * FROM Patient;

-- Step 19: Update patient DOB
UPDATE Patient SET patient_DOB = TO_DATE('2000-12-01', 'YYYY-MM-DD') WHERE patient_id = 1;

-- Step 20: Select all records after DOB update
SELECT * FROM Patient;

-- Step 21: Update patient gender
UPDATE Patient SET patient_gender = 'Male' WHERE patient_id = 1;

-- Step 22: Select all records after gender update
SELECT * FROM Patient;

-- Step 23: Insert more patient records
INSERT INTO Patient (patient_name, patient_gender, patient_DOB, patient_email, patient_address, patient_contact)
VALUES ('Ali', 'male', TO_DATE('2002-12-27', 'YYYY-MM-DD'), 'Ali@gmail.com', 'Bisma Town', '03313198649');

INSERT INTO Patient (patient_name, patient_gender, patient_DOB, patient_email, patient_address, patient_contact)
VALUES ('Ahmed', 'male', TO_DATE('2002-11-20', 'YYYY-MM-DD'), 'Ahmed@gmail.com', 'Muka Chowk', '03316734986');

-- Step 24: Select all records from Patient table
SELECT * FROM Patient;
------------------------------------------------------------------------------------------------------------------------------
-- Step 1: Create Appointment Table
CREATE TABLE Appointment (
    patient_id NUMBER,
    doctor_id NUMBER,
    appointment_date DATE,  -- Use DATE for appointment_date
    appointment_time VARCHAR2(20) UNIQUE,  -- Keep VARCHAR2 for appointment_time
    appointment_reason VARCHAR2(100),
    PRIMARY KEY (doctor_id, patient_id),  -- Composite primary key
    FOREIGN KEY (doctor_id) REFERENCES Doctor (doctor_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES Patient (patient_id) ON DELETE CASCADE
);

-- Step 2: Insert data into Appointment Table
-- First, get the doctor_id and patient_id and then insert into the Appointment table
INSERT INTO Appointment (doctor_id, patient_id, appointment_date, appointment_time, appointment_reason)
SELECT doctor_id, patient_id, TO_DATE('2023-06-20', 'YYYY-MM-DD'), '12am', 'stomachache'
FROM Doctor, Patient
WHERE Doctor.doctor_name = 'Ali' AND Patient.patient_name = 'Ahmed';

-- View records from Doctor table (optional)
SELECT * FROM Doctor;

-- Step 3: Select all records from Appointment table
SELECT * FROM Appointment;

-- Step 4: Update Appointment (update appointment details)
UPDATE Appointment
SET appointment_date = TO_DATE('2023-12-04', 'YYYY-MM-DD'),
    appointment_time = '12pm', 
    appointment_reason = 'stomachache'
WHERE doctor_id = (SELECT doctor_id FROM Doctor WHERE doctor_name = 'Ali') 
  AND patient_id = (SELECT patient_id FROM Patient WHERE patient_name = 'Ahmed');

-- Step 5: Delete Appointment (delete based on doctor and patient)
DELETE FROM Appointment
WHERE doctor_id = (SELECT doctor_id FROM Doctor WHERE doctor_name = 'Fiza')
  AND patient_id = (SELECT patient_id FROM Patient WHERE patient_name = 'Ali');

-- Step 6: View all Appointment data
SELECT doctor_id, patient_id, appointment_date, appointment_time, appointment_reason 
FROM Appointment;

----------------------------------------------------------------------------------------------------------
-- Step 1: Create Prescription2 Table
CREATE TABLE Prescription2 (
    medicine_id NUMBER,  -- Use NUMBER for integer type
    potency VARCHAR2(20),  -- Use VARCHAR2 for strings
    patient_id NUMBER,  -- Use NUMBER for integer type
    doctor_id NUMBER,  -- Use NUMBER for integer type
    when_to_take VARCHAR2(100) NOT NULL,
    amount NUMBER NOT NULL,  -- Use NUMBER for integer type
    special_instruction VARCHAR2(100) NOT NULL,
    duration VARCHAR2(100) NOT NULL,
    PRIMARY KEY (medicine_id, patient_id, doctor_id),  -- Composite primary key
    FOREIGN KEY (medicine_id) REFERENCES medicine4 (medicine_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctor (doctor_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patient (patient_id) ON DELETE CASCADE
);

-- Step 2: Insert data into Prescription2 Table
-- First, get the medicine_id, patient_id, and doctor_id, then insert
INSERT INTO Prescription2 (medicine_id, potency, patient_id, doctor_id, when_to_take, amount, special_instruction, duration)
SELECT medicine_id, '120mg', patient_id, doctor_id, 'evening', 2, 'rest well', '3 days'
FROM medicine4, patient, doctor
WHERE medicine4.medicine_name = 'zolp' 
  AND patient.patient_name = 'Khusama Khan'
  AND doctor.doctor_name = 'Ahmed';

-- Step 3: Select all records from Prescription2
SELECT * FROM Prescription2;

-- Step 6: Update Prescription2 (update specific prescription by medicine_name, doctor_name, and patient_name)
UPDATE Prescription2
SET potency = '150mg', when_to_take = 'morning', amount = 2, special_instruction = 'rest well', duration = '2 days'
WHERE medicine_id = (
    SELECT medicine_id 
    FROM medicine4 
    WHERE medicine_name = 'zolp' AND ROWNUM = 1
)
AND patient_id = (
    SELECT patient_id 
    FROM patient 
    WHERE patient_name = 'Khusama Khan' AND ROWNUM = 1
)
AND doctor_id = (
    SELECT doctor_id 
    FROM doctor 
    WHERE doctor_name = 'Ahmed' AND ROWNUM = 1
);


-- Step 7: Delete from Prescription2 (delete prescription based on doctor_name, patient_name, and medicine_name)
DELETE FROM Prescription2
WHERE medicine_id = (
    SELECT medicine_id 
    FROM medicine4 
    WHERE medicine_name = 'brufen' AND ROWNUM = 1
)
AND patient_id = (
    SELECT patient_id 
    FROM patient 
    WHERE patient_name = 'Ali' AND ROWNUM = 1
)
AND doctor_id = (
    SELECT doctor_id 
    FROM doctor 
    WHERE doctor_name = 'Ahmed' AND ROWNUM = 1
)
AND potency = '120mg';

----------------------------------------------------------------------------------------------------------------------------------------
-- Create Supplier Table
CREATE TABLE Supplier (
    Supplier_id NUMBER PRIMARY KEY,  -- We will manually assign this using the sequence
    Supplier_name VARCHAR2(100) NOT NULL,
    Supplier_address VARCHAR2(100) NOT NULL,
    Supplier_phone VARCHAR2(11) NOT NULL UNIQUE,
    Supplier_email VARCHAR2(100) NOT NULL
);
ALTER TABLE Supplier
ADD COLUMN supplier_status VARCHAR2(20) NOT NULL DEFAULT 'Active';


-- Insert record into Supplier
INSERT INTO Supplier (Supplier_name, Supplier_address, Supplier_phone, Supplier_email)
VALUES ('Saad', 'Gulshan-e-Iqbal', '021-1234212', 'saad@gmail.com');

INSERT INTO Supplier (Supplier_name, Supplier_address, Supplier_phone, Supplier_email)
VALUES ('Ahmed', 'Korangi', '021-1234213', 'ahmed@gmail.com');


-- Update record in Supplier
UPDATE Supplier
SET Supplier_name = 'Saad', Supplier_address = 'Gulshan-e-Iqbal', 
    Supplier_phone = '2338383', Supplier_email = 'ahmed@gmail.com'
WHERE Supplier_name = 'Saad';

-- Delete record from Supplier
DELETE FROM Supplier WHERE Supplier_name = 'Saad';

-- Select Supplier record by name (this will return no rows since 'Azaan' doesn't exist)
SELECT Supplier_name, Supplier_address, Supplier_phone, Supplier_email
FROM Supplier WHERE Supplier_name = 'Azaan';

-- Update Supplier name where Supplier_id is 1
UPDATE Supplier
SET Supplier_name = 'Fiza'
WHERE Supplier_id = 2;

-- Update Supplier address where Supplier_id is 1
UPDATE Supplier
SET Supplier_address = 'Defence'
WHERE Supplier_id = 1;
select * from supplier;
-----------------------------------------------------------------------------------------------------------------
CREATE TABLE stock (
    stock_id NUMBER PRIMARY KEY,  -- Auto-increment via sequence
    stock_name VARCHAR2(100) NOT NULL,
    expiry DATE NOT NULL,
    quantity INT NOT NULL,
    rate INT NOT NULL,
    medicine_id INT NOT NULL,
    supplier_id INT NOT NULL,
    FOREIGN KEY (medicine_id) REFERENCES medicine4 (medicine_id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES supplier (supplier_id) ON DELETE CASCADE
);

INSERT INTO stock (stock_name, expiry, quantity, rate, medicine_id, supplier_id)
VALUES ('zolp', TO_DATE('2023-01-12', 'YYYY-MM-DD'), 50, 20, 
        (SELECT medicine_id FROM medicine4 WHERE medicine_name = 'zolp' AND ROWNUM = 1), 
        (SELECT supplier_id FROM supplier WHERE supplier_name = 'Fiza' AND ROWNUM = 1));
        
UPDATE stock
SET stock_name = 'brufen',
    medicine_id = (SELECT medicine_id FROM medicine4 WHERE medicine_name = 'zolp' AND ROWNUM = 1),
    supplier_id = (SELECT supplier_id FROM supplier WHERE supplier_name = 'Fiza' AND ROWNUM = 1),
    expiry = TO_DATE('2023-12-01', 'YYYY-MM-DD'),
    quantity = 100,
    rate = 200
WHERE stock_id = 1;

DELETE FROM stock 
WHERE stock_id = 1 
  AND stock_name = 'brufen';

------------------------------------------------------------------------------------------------------------------
CREATE TABLE Orders (
    order_id NUMBER PRIMARY KEY, -- Auto-incremented using sequence
    patient_id NUMBER NOT NULL,
    order_date DATE NOT NULL, -- Use DATE type for order_date
    FOREIGN KEY (patient_id) REFERENCES Patient (patient_id) ON DELETE CASCADE
);
ALTER TABLE Orders ADD order_status VARCHAR(50) DEFAULT 'Pending';



INSERT INTO Orders (patient_id, order_date)
SELECT 
    p.patient_id,
    TO_DATE('2023-06-23', 'YYYY-MM-DD'),
   
FROM 
    Patient p,
WHERE 
    p.patient_name = 'khusama Khan';
---------------------------------------------------------------------------------------------------------------
-- Create the OrderItems table with auto-incremented primary key
-- Creating the OrderItems table with foreign keys and cascading delete
CREATE TABLE OrderItems (
    orderItem_id NUMBER PRIMARY KEY, 
    order_id NUMBER NOT NULL,
    medicine_id NUMBER NOT NULL,
    Quantity NUMBER NOT NULL,
    CONSTRAINT fk_order_id FOREIGN KEY (order_id) REFERENCES Orders (order_id) 
        ON DELETE CASCADE,
    CONSTRAINT fk_medicine_id FOREIGN KEY (medicine_id) REFERENCES medicine4 (medicine_id) 
        ON DELETE CASCADE
);
DELIMITER $$

CREATE TRIGGER check_stock_before_insert
BEFORE INSERT ON OrderItems
FOR EACH ROW
BEGIN
    DECLARE v_stock_quantity INT;

    -- Get the current stock quantity of the medicine
    SELECT quantity INTO v_stock_quantity
    FROM stock
    WHERE medicine_id = NEW.medicine_id;

    -- Check if stock is insufficient
    IF v_stock_quantity < NEW.Quantity THEN
        -- Raise an error if there's not enough stock
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock available for this medicine';
    END IF;
END$$

DELIMITER ;

-------------------------------------------------------------------------------------------------------------------------
-- Create the Bill table with sequence and trigger for auto-incrementing BillID
CREATE TABLE Bill (
    BillID NUMBER PRIMARY KEY,  -- BillID will be populated via a sequence and trigger
    order_id NUMBER NOT NULL,
    patient_id NUMBER NOT NULL,
    BILL_Date DATE NOT NULL,
    TotalAmount NUMBER NOT NULL,
    PaymentMethod VARCHAR2(50),
    FOREIGN KEY (order_id) REFERENCES Orders(order_id)
);


-- Query to select the details for a specific order (order_id = 1)
SELECT
    oi.order_id,
    m.medicine_name,
    oi.quantity,
    m.medicine_price,
    oi.quantity * m.medicine_price AS Total
FROM OrderItems oi
JOIN medicine4 m ON oi.medicine_id = m.medicine_id
WHERE oi.order_id = 1;

-- Insert the bill based on the order and related medicine details
INSERT INTO Bill (order_id, patient_id, Bill_Date, TotalAmount, PaymentMethod)
SELECT 
    oi.order_id,
    (SELECT patient_id FROM Orders WHERE order_id = oi.order_id),
    SYSDATE,  -- Oracle uses SYSDATE for current date and time
    SUM(oi.quantity * m.medicine_price),
    'cheque'
FROM OrderItems oi
JOIN medicine4 m ON oi.medicine_id = m.medicine_id
WHERE oi.order_id = 1
GROUP BY oi.order_id;

-- Select all records from the Bill table
SELECT * FROM Bill;
-------------------------------------------------------------------------------------------------
-- Create the Feedback table with sequence and trigger for auto-incrementing feedback_id
CREATE TABLE Feedback (
    feedback_id NUMBER PRIMARY KEY,  -- feedback_id will be populated via a sequence and trigger
    patient_id NUMBER NOT NULL,
    order_id NUMBER NOT NULL,
    Rating NUMBER NOT NULL,
    comments VARCHAR2(255),
    FOREIGN KEY (order_id) REFERENCES Orders(order_id),
    FOREIGN KEY (patient_id) REFERENCES Patient(patient_id),
);


-- Select feedback data
SELECT feedback_id, order_id, patient_id, Rating, comments
FROM Feedback;
-------------------------------------------------------------------------------------------------------------------------

