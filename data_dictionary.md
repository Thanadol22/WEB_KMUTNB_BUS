# Data Dictionary: KMUTNB Shuttle Tracker

เอกสารนี้รวบรวมโครงสร้างข้อมูล (Data Dictionary) ที่ใช้งานอยู่ในฐานข้อมูล Firebase (Firestore และ Realtime Database) สำหรับระบบ KMUTNB Shuttle Tracker โดยรวบรวมจากการวิเคราะห์ Source code ในฝั่ง Web Admin และ API Services

## 1. Collection: `users`
เก็บข้อมูลผู้ใช้งานระบบทั้งหมด (นักศึกษา, พนักงานขับรถ, ผู้ดูแลระบบ)

| Field Name | Data Type | Description |
|---|---|---|
| `id` / `uid` | String | รหัสผู้ใช้งาน (Document ID) |
| `name` | String | ชื่อ-นามสกุล ของผู้ใช้งาน |
| `username` | String | ชื่อผู้ใช้งานสำหรับเข้าสู่ระบบ |
| `password` | String | รหัสผ่านผู้ใช้งาน |
| `phone` | String | เบอร์โทรศัพท์ |
| `role` | String | บทบาทของผู้ใช้งาน (`student`, `driver`, `admin`) |
| `status` | String | สถานะการใช้งานบัญชี (เช่น `active`, `inactive`) |
| `fcm_token` | String | Token ระบบสำหรับส่ง Push Notification ให้แต่ละอุปกรณ์ |
| `created_at` | String | วันที่และเวลาที่สร้างบัญชี |

## 2. Collection: `buses`
เก็บข้อมูลทะเบียนเเละสถานะของรถรับ-ส่งภายในมหาวิทยาลัย

| Field Name | Data Type | Description |
|---|---|---|
| `id` / `bus_id` | String | รหัสอ้างอิงรถ (Document ID) |
| `bus_number` / `name`| String | ป้ายทะเบียนรถ หรือหมายเลขข้างรถ |
| `status` | String | สถานะของรถ (เช่น พร้อมให้บริการ, หยุดให้บริการ, ซ่อมบำรุง) |

> **หมายเหตุ:** สำหรับที่อยู่ GPS ปัจจุบันของรถจะถูกยิงจาก Mobile App และจัดเก็บใน **Realtime Database (RTDB)** โดยอ้างอิงผ่าน Key `bus_id` 

## 3. Collection: `schedules` (และ `detailed_schedules`)
เก็บข้อมูลตารางการเดินรถและจุดจอดต่างๆ โดยออกแบบจัดเก็บแยกตาม "รอบ" เเละ "จุดจอด"

| Field Name | Data Type | Description |
|---|---|---|
| `id` | String | รหัสรอบและจุดจอด (รูปแบบเช่น `round_01_stop_01`) |
| `round` | Integer | รอบการทำงานวิ่งรถ (เช่น 1, 2, 3...) |
| `route_name` | String | ชื่อป้ายรถเมล์ / จุดจอดรับ-ส่ง |
| `start_time` | String | เวลาตั้งต้น / เวลาถึงป้าย (ETA) |
| `end_time` | String | เวลาออกจากป้าย |
| `bus_id` | String | รหัสรถประจำรอบนั้น (ที่ผูกกับตาราง) |
| `latitude` | Float | พิกัดละติจูดของจุดจอด (ถ้ามีระบุตอนกำหนดจุด) |
| `longitude` | Float | พิกัดลองจิจูดของจุดจอด (ถ้ามีระบุตอนกำหนดจุด) |

## 4. Collection: `locations`
คลังข้อมูลจุดจอดรับ-ส่ง สำหรับอ้างอิงและพล็อตพิกัดลงหน้าแผนที่ในเว็บ

| Field Name | Data Type | Description |
|---|---|---|
| `id` | String | รหัสจุดจอดอ้างอิง (Document ID) |
| `name` | String | ชื่อป้ายรอรถ/จุดจอด |
| `lat` | Float | ค่าพิกัดละติจูดของป้ายรถ |
| `lng` | Float | ค่าพิกัดลองจิจูดของป้ายรถ |
| `updated_at` | String | เวลาของการแก้ไขข้อมูลจุดตำแหน่งล่าสุด |

## 5. Collection: `ticket_reports`
เก็บข้อมูลจำนวนตั๋วโดยสารแบบกระดาษที่คนขับรถเป็นผู้นับและรายงานเข้าระบบในแต่ละรอบ

| Field Name | Data Type | Description |
|---|---|---|
| `id` | String | รหัสเอกสารการรายงานตั๋ว |
| `ticket_count` | Integer | จำนวนตั๋วที่มีการนับและส่งรายงานเข้ามา |
| `bus_id` | String | รหัสอ้างอิงรถคันที่รายงาน |
| `driver_id` | String | รหัสอ้างอิงผู้ขับรถ (สามารถนำไปเช็คซ้ำใน `users`) |
| `round_time` | String | ช่วงรอบเวลาของการวิ่งที่คนขับทำการรายงาน |
| `timestamp` / `created_at`| String / Number | วันเวลาที่มีการส่งรายงานจาก Mobile App |

## 6. Collection: `issue_reports`
เก็บบันทึกประวัติการแจ้งเรื่อง/แจ้งปัญหาที่ถูกส่งจากฝั่งนักศึกษาไปยังผู้ดูแลระบบ 

| Field Name | Data Type | Description |
|---|---|---|
| `id` | String | รหัสเอกสารการแจ้งปัญหา |
| `student_id` | String | รหัสบัญชีผู้ใช้ (นักศึกษา) ผู้แจ้งปัญหา |
| `topic` | String | หัวข้อหลักของการแจ้งปัญหา |
| `description` | String | คำอธิบายรายละเอียดของปัญหาที่พบ |
| `status` | String | สถานะการจัดการ (`pending` = รอดำเนินการ, `resolved` = แก้ไขแล้ว) |
| `timestamp` / `created_at`| String / Number | วันและเวลาที่สร้างรายการแจ้งเรื่อง |

## 7. Collection: `operation_history`
เก็บบันทึกประวัติการเดินรถแบบ Real-time พร้อมสถานะว่าเดินรถตรงเวลา ล่าช้า หรือก่อนเวลา 

| Field Name | Data Type | Description |
|---|---|---|
| `id` | String | รหัสเอกสาร (Document ID เช่น `HISTORY_round_04_xxx`) |
| `actualTime` | String | เวลาจริงที่ระบบบันทึกสถานะได้ (เช่น `2026-04-10 09:03:00`) |
| `busId` | String | รหัสรถรับ-ส่งที่กำลังวิ่ง (เช่น `BUS01`) |
| `currentStop` | String | ชื่อป้ายปัจจุบัน หรือสถานะระหว่างทาง (เช่น `กำลังเดินทาง (ไม่อยู่ที่ป้าย)`) |
| `lat` | Float | พิกัดละติจูด ณ จุดที่บันทึกประวัติ |
| `lon` | Float | พิกัดลองจิจูด ณ จุดที่บันทึกประวัติ |
| `round_id` | String | รหัสอ้างอิงรอบที่วิ่ง (อ้างอิงจาก `schedules` เช่น `round_04`) |
| `route` | String | ชื่อสายการวิ่ง หรือเส้นทางที่รถวิ่ง |
| `scheduleTime` | String | เวลาตามตารางที่วางแผนไว้ |
| `status` | String | สถานะการเดินรถเมื่อเทียบกับตารางเวลา (เช่น `ON_TIME`, `LATE`, `EARLY`) |
| `timestamp` | Number | หมายเลข Unix Timestamp ณ เวลาที่บันทึกประวัติ |
