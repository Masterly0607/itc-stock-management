# 🟩 Phase 1 – Scope & Requirements

## 1. Overview
The **Stock Management System** is a web application to manage products, stock, and transfers between **HQ (Super Admin)**, **Admin (Province)**, and **Distributor (District)**.  
It ensures accurate stock tracking, prevents negative stock, and applies the **Pay-Before-Deliver** rule for distributors.

---

## 2. Main Roles

| Role | Description | Permissions |
|------|--------------|-------------|
| **Super Admin** | Controls the whole system | - Manage all provinces, districts, and branches<br>- Create and manage users (Admins & Distributors)<br>- Approve and monitor all stock transfers<br>- Manage products, categories, and prices<br>- Manage suppliers and purchase orders<br>- View all reports and activities<br>- Configure system settings and permissions |
| **Admin** | Manages one province branch | - Approve distributor stock requests<br>- Manage province-level stock and transfers<br>- View province reports<br>- Manage distributors in the same province |
| **Distributor** | Manages one district branch | - Create stock requests to Admin<br>- Receive approved stock<br>- Record sales and payments<br>- View own branch transactions |

---

## 3. Main Features

### 🧾 Product Management
- Manage **Categories**, **Products**, **Units**, and **Prices**
- Each product belongs to a category and can have multiple units (e.g., Box, Piece)
- Only Super Admin can add or edit products

### 🏭 Supplier & Purchasing
- Super Admin can manage **Suppliers** (name, contact, tax ID, etc.)
- HQ can create **Purchase Orders (POs)** to receive new stock
- Receiving a PO automatically increases HQ stock and logs a **Ledger IN (PURCHASE)**

### 🔁 Stock Request & Transfer
- **Distributor** requests stock from **Admin**
- **Admin** reviews and approves requests
- Stock is transferred from HQ or Admin branch to Distributor
- Stock levels update automatically in both branches

### 💰 Sales & Payment
- Distributor records **Sales Orders** and **Payments**
- System checks “Pay-Before-Deliver” before allowing delivery
- Sales automatically reduce stock

### 🧮 Stock Control
- Prevents **negative stock**
- Tracks all movements in an **Inventory Ledger**
- Admins and Distributors can only view their own branch data

---

## 4. Main Database Tables

| Group | Tables |
|--------|--------|
| **Catalog** | Categories, Products, Units, Prices |
| **Location** | Provinces, Districts, Branches |
| **User Management** | Users, Roles |
| **Inventory** | Stock Levels, Inventory Ledger |
| **Operations** | Stock Requests, Transfers, Sales Orders, Payments, Adjustments, **Suppliers**, **Purchase Orders** |

---

## 5. System Rules
- ❌ No negative stock allowed  
- 💵 Distributor must **pay before delivery**  
- 🏢 Each **province has one Admin branch**  
- 🔒 Users can only see their own branch data  
- 🧾 All stock and sales actions are recorded in the ledger  
- ⚙️ Super Admin can see and manage everything

---

## 6. Goal
To clearly define what the system does, who can do what, and how stock flows — ensuring a shared understanding before design and development.

---

## 7. Deliverables
✅ `scope.md` file created and committed  
✅ Roles, features, and rules clearly defined  
✅ Supervisor approval to move to **Phase 2 – System Design**

---

**Project:** Stock Management System (ITC Internship Project)  
**Author:** Sok Masterly  
**Phase:** 1 – Scope & Requirements 
