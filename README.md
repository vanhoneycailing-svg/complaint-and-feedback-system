# Complaint & Feedback Reporting System (CFRS)
## PHP/HTML + XAMPP CRUD Application

---

## 📁 File Structure

```
complaint_system/
├── index.php               ← Redirects to login
├── login.php               ← Login page
├── logout.php              ← Destroys session
├── dashboard.php           ← Main dashboard
├── complaints.php          ← CRUD for complaints (main module)
├── users.php               ← User management (Admin only)
├── notifications.php       ← Notifications center
├── reports.php             ← Analytics & reports (Admin only)
├── database.sql            ← DB schema + seed data
│
├── css/
│   └── style.css           ← Main stylesheet
│
├── includes/
│   ├── db.php              ← Database connection
│   ├── auth.php            ← Session helpers + utilities
│   └── sidebar.php         ← Navigation sidebar partial
│
└── uploads/                ← Photo uploads (auto-created)
```

---

## ⚙️ Setup Instructions

### Step 1: Install & Start XAMPP
1. Download XAMPP from https://www.apachefriends.org/
2. Start **Apache** and **MySQL** from the XAMPP Control Panel

### Step 2: Copy Files
1. Copy the entire `complaint_system/` folder into:
   - **Windows:** `C:\xampp\htdocs\complaint_system\`
   - **Mac/Linux:** `/Applications/XAMPP/htdocs/complaint_system/`

### Step 3: Create the Database
1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **"New"** to create a new database (or use the SQL tab)
3. Click the **SQL** tab
4. Open `database.sql` from this folder
5. Copy-paste the entire contents into the SQL window
6. Click **Go** to execute

### Step 4: Run the Application
Open your browser and go to:
```
http://localhost/complaint_system/
```

---

## 👤 Demo Login Accounts

| Role      | Email                   | Password   |
|-----------|-------------------------|------------|
| Admin     | admin@system.com        | password   |
| Staff     | staff@system.com        | password   |
| Resident  | resident@system.com     | password   |

---

## 🔑 Features by Role

### 🛡 Admin
- View ALL complaints dashboard
- Create/Read/Update/Delete complaints
- Assign complaints to staff
- Manage users (Create/Edit/Delete)
- View reports & analytics
- Receive notifications

### 👷 Staff (Department)
- View complaints assigned to them
- Update progress notes
- Mark complaints as resolved
- Upload proof-of-resolution photos
- Communicate status updates

### 🏠 Resident/Student
- Submit new complaints (with photo)
- Track complaint status in real-time
- Receive automated notifications
- Rate and provide feedback after resolution

---

## 📋 CRUD Summary

| Module      | Create | Read | Update | Delete |
|-------------|--------|------|--------|--------|
| Complaints  | ✅     | ✅   | ✅     | ✅ (Admin) |
| Users       | ✅     | ✅   | ✅     | ✅ (Admin) |
| Notifications | Auto | ✅  | ✅ (mark read) | — |

---

## 🗄️ Database Tables

1. **users** – id, full_name, email, password, role, department, created_at
2. **complaints** – id, complaint_no, user_id, title, description, category, status, photo, assigned_to, resolution_photo, resolution_notes, rating, feedback, timestamps
3. **notifications** – id, user_id, complaint_id, message, is_read, created_at

---

## 🛠 Tech Stack
- **Backend:** PHP 7.4+
- **Database:** MySQL (via MySQLi)
- **Frontend:** HTML5, CSS3, Vanilla JS
- **Server:** Apache via XAMPP
- **Fonts:** Sora + JetBrains Mono (Google Fonts)

---

## 📝 Notes
- Photo uploads are stored in `uploads/` folder (max 5MB, JPG/PNG/GIF/WEBP)
- Passwords are hashed using PHP `password_hash()` (bcrypt)
- All inputs are sanitized with `htmlspecialchars()` and prepared statements
- Session-based authentication with role-based access control
