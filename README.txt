Land Inventory System

Setup:
1. Copy this folder into your XAMPP htdocs or Laragon www directory.
2. Import the file `database.sql` into MySQL using phpMyAdmin or MySQL Workbench.
3. Make sure MySQL username/password in `config/db.php` match your local setup.
4. Start Apache and MySQL in XAMPP.
5. Open in browser:
   http://localhost/Land%20Inventory%20System/

Default database connection:
- Host: 127.0.0.1
- Port: 3306
- Database: Land Inventory
- Username: root
- Password: root

Notes:
- If Apache is not running, the website will not open even if the project files are in `htdocs`.
- If the PC is turned off, the website will be unavailable until the computer is on again and Apache is started.
- To access the site from another device on the same network, Apache must be running and Windows Firewall must allow port 80.

Main modules:
- Dashboard
- Barangay Management
- Lot Management
- Land Use Management
- Search
- Reports
