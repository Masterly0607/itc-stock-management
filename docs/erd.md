# 🟦 Phase 2 – System Design
Design plan for the **Stock Management System (ITC Internship Project)** based on the approved Phase 1 Scope.

---

## 1. Architecture
- **Framework:** Laravel + Filament (Admin Panel)  
- **Auth / RBAC:** Laravel + Spatie Permissions  
- **Database:** MySQL (InnoDB, utf8mb4)  
- **Pattern:** Transactional posting → `inventory_ledger` and `stock_levels`  
- **Isolation:** Queries scoped per branch (role based)  

---

## 2. Entity Relationship Diagram (ERD Concept)
```
Province 1-* District  
Province 1-* Branch (province branch = district NULL)  
District 1-* Branch (district branch)  
Branch 1-* User  

Category 1-* Product  
Product 1-* Price (global or province)  
Unit 1-* ProductUnit (optional)  

Branch - Product → StockLevel  
StockRequest 1-* StockRequestItem  
Transfer 1-* TransferItem  
SalesOrder 1-* SalesOrderItem  
SalesOrder 1-* Payment  
Adjustment 1-* AdjustmentItem  
InventoryLedger (polymorphic to any posted doc)
```

---

## 3. Main Tables (Key Fields)

### 3.1 Catalog
| Table | Key Fields |
|--------|------------|
| **categories** | name, code, is_active |
| **units** | name, symbol, base_ratio |
| **products** | name, sku, barcode, category_id, brand, unit_base_id, is_active |
| **prices** | product_id, province_id (nullable), unit_id, price, currency, starts_at, ends_at (nullable), is_active |

### 3.2 Location & Branch
| Table | Key Fields |
|--------|------------|
| **provinces** | name, code |
| **districts** | province_id, name, code |
| **branches** | name, code, type (HQ/PROVINCE/DISTRICT), province_id, district_id (nullable) |

### 3.3 Users & Roles
| Table | Key Fields |
|--------|------------|
| **users** | name, email, password, branch_id, status |
| **roles / permissions** | Spatie default tables |

### 3.4 Inventory
| Table | Key Fields |
|--------|------------|
| **stock_levels** | branch_id, product_id, unit_id, on_hand, reserved (unique) |
| **inventory_ledger** | txn_type, txn_id, branch_id, product_id, unit_id, qty_delta, reference, notes, posted_at, posted_by |

### 3.5 Operations
| Table | Key Fields |
|--------|------------|
| **stock_requests** | requested_by_user_id, request_branch_id, source_branch_id, status, requested_at, approved_at, approved_by |
| **stock_request_items** | stock_request_id, product_id, unit_id, qty_requested, qty_approved |
| **transfers** | from_branch_id, to_branch_id, status, dispatched_at, received_at, ref_no |
| **transfer_items** | transfer_id, product_id, unit_id, qty |
| **sales_orders** | branch_id, customer_name, status, requires_prepayment, total_amount, currency, posted_at, posted_by |
| **sales_order_items** | sales_order_id, product_id, unit_id, qty, unit_price, line_total |
| **payments** | sales_order_id, amount, currency, method, paid_at, received_by |
| **adjustments** | branch_id, reason, status, posted_at, approved_by |
| **adjustment_items** | adjustment_id, product_id, unit_id, qty_delta, note |

---

## 4. Workflow / State Machine
| Document | States | Notes |
|-----------|---------|-------|
| **Stock Request** | PENDING → APPROVED/REJECTED/CANCELLED | Approval creates Transfer (DRAFT) |
| **Transfer** | DRAFT → DISPATCHED → RECEIVED → CANCELLED | Stock out/in + ledger |
| **Sales Order** | DRAFT → POSTED | Delivery only if payment ≥ total |
| **Adjustment** | DRAFT → POSTED | Direct ± on_hand + ledger |

---

## 5. Posting Logic (Atomic)
1. **Transfer Dispatch (Source)** → validate `on_hand ≥ qty` → `on_hand -= qty` → ledger out  
2. **Transfer Receive (Destination)** → `on_hand += qty` → ledger in  
3. **Sales Delivery (Distributor)** → if paid enough → `on_hand -= qty` → ledger out  
4. **Adjustment Post** → apply `qty_delta` (±) → ledger entry  

_All in DB transactions._

---

## 6. Permissions & Scoping
| Action | Super Admin | Admin (Province) | Distributor (District) |
|--------|--------------|------------------|------------------------|
| Manage Products/Categories | ✅ | ❌ | ❌ |
| CRUD Users | ✅ (Admin + Distributor) | ✅ (Distributors in own province) | ❌ |
| View Data | All branches | Own province | Own district |
| Approve Stock Request | ✅ | ✅ | ❌ |
| Create Stock Request | ❌ | ❌ | ✅ |
| Dispatch/Receive Transfer | ✅ | ✅ (own province) | ✅ (receive own) |
| Sales & Payments | View all | View province | ✅ own |
| Adjustments | ✅ | ✅ | ✅ (limited) |

**Query Rules**  
- Super Admin → no filter  
- Admin → `province_id`  
- Distributor → `branch_id`

---

## 7. Filament Resources (MVP)
- Catalog: Category, Product (+Prices), Unit  
- Location: Province, District, Branch  
- Users: UserResource (auto assign branch based on role)  
- Operations: StockRequest, Transfer, SalesOrder, Payment, Adjustment  
- Reports: Stock On Hand, Ledger, Sales Summary  

---

## 8. Validation Rules
- No negative stock  
- One Admin branch per province  
- Unique (branch, product, unit) for stock  
- No overlapping prices for same product/unit/province  
- Posted docs are immutable  

---

## 9. Indexes & Performance
- `stock_levels(branch_id, product_id, unit_id)` UNIQUE  
- `inventory_ledger(branch_id, product_id, posted_at)` INDEX  
- FK fields indexed  
- Branch/type indexes for filtering  

---

## 10. Migration Order
1. Provinces, Districts, Branches  
2. Users (+ Roles/Permissions)  
3. Categories, Units, Products, Prices  
4. Stock Levels  
5. Stock Requests + Items  
6. Transfers + Items  
7. Sales Orders + Items + Payments  
8. Adjustments + Items  
9. Inventory Ledger  

---

## 11. Core Sequences
**A) Create Distributor**  
- Admin creates user → role = Distributor  
- Province auto-filled, district restricted to province  
- System sets `branch_id` to district branch  

**B) Request → Approve → Transfer**  
- Distributor requests stock  
- Admin approves → Transfer created  
- Dispatch = stock out (source)  
- Receive = stock in (dest)  

**C) Sales with Pay-Before-Deliver**  
- Distributor creates Sales Order  
- Records Payment  
- If paid enough → Deliver → Stock - Qty  

**D) Adjustment**  
- Draft items ± qty  
- Post → Update stock + ledger  

---

## 12. Acceptance Checklist (Phase 2)
✅ ERD complete and approved  
✅ Tables and keys defined  
✅ Posting logic documented  
✅ Permissions matrix matches scope  
✅ Index plan ready  
✅ Resources mapped for Filament  
➡️ Ready for **Phase 3 – Database & Seeding**

---

**Project:** Stock Management System (ITC Internship Project)  
**Phase:** 2 – System Design  
**Author:** Sok Masterly
