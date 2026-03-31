<?php
/**
 * Product Management Form - Complete CRUD with validations (Interview Ready)
 * Features: 
 * 1. Form validation (Name≥3 letters, Desc≥5, Zipcode=6 digits)
 * 2. Dynamic product fields with validation (letters, int, decimal, unique names)
 * 3. Cascading dropdowns Country→City→State with edit preservation
 * 4. Add/Edit/Delete in single page
 * 5. Form clears after successful submit
 * Database tables: main_forms, form_products, countries, cities, states
 */

// ======================================
// 1. DATABASE CONNECTION using mysqli
// ======================================
$servername = "localhost";  // MySQL server
$username = "root";         // Default XAMPP user
$password = "";             // Default XAMPP password (empty)
$dbname = "devoult_db";     // Database name

// mysqli_connect() - Establishes connection to MySQL
$conn = mysqli_connect($servername, $username, $password, $dbname);
// mysqli_connect_error() - Returns error if connection fails
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ======================================
// 2. VARIABLES INITIALIZATION
// ======================================
$message = '';  // Success message
$error = '';    // Error message

// Reset edit data by default (form will be blank for new entry)
$edit_data = null;
$edit_products = [];

// ======================================
// 3. HANDLE FORM SUBMISSION (POST REQUESTS)
// ======================================
if ($_POST) {  // Check if form submitted
    $action = $_POST['action'] ?? '';  // Get action: 'create', 'update', 'delete'

    if ($action == 'create' || $action == 'update') {
        // Extract and sanitize form data using trim()
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $zipcode = trim($_POST['zipcode'] ?? '');
        $country_id = $_POST['country_id'] ?? 0;
        $city_id = $_POST['city_id'] ?? 0;
        $state_id = $_POST['state_id'] ?? 0;
        $products = $_POST['products'] ?? [];  // Array of product data

        // ================================
        // 3.1 BASIC FORM FIELD VALIDATIONS
        // ================================
        // strlen() + preg_match() for name validation
        if (strlen($name) < 3 || !preg_match('/^[a-zA-Z\s]+$/', $name)) {
            $error = 'Name must be at least 3 characters, letters only.';
        // strlen() for description validation
        } elseif (strlen($description) < 5) {
            $error = 'Description must be at least 5 characters.';
        // preg_match() for exact 6 digits zipcode
        } elseif (!preg_match('/^\d{6}$/', $zipcode)) {
            $error = 'Zipcode must be exactly 6 digits.';
        } elseif (!$country_id || !$city_id || !$state_id) {
            $error = 'Please select Country, City, and State.';
        } else {
            // ================================
            // 3.2 DYNAMIC PRODUCT FIELDS VALIDATION
            // ================================
            $valid_products = [];  // Store validated products
            $product_error = false;
            foreach ($products as $prod) {  // Loop through all product rows
                $pname = trim($prod['name'] ?? '');
                $pqty = trim($prod['qty'] ?? '');
                $pprice = trim($prod['price'] ?? '');

                // Individual field validations with break on first error
                if ($pname && !preg_match('/^[a-zA-Z\s]+$/', $pname)) {
                    $product_error = true; 
                    break;
                }
                if ($pqty && (!is_numeric($pqty) || $pqty <= 0 || !preg_match('/^\d+$/', $pqty))) {
                    $product_error = true; 
                    break;
                }
                if ($pprice && (!is_numeric($pprice) || !preg_match('/^\d+(\.\d{1,2})?$/', $pprice))) {
                    $product_error = true; 
                    break;
                }

                // Only add complete valid products
                if ($pname && $pqty && $pprice) {
                    $valid_products[] = [$pname, (int)$pqty, (float)$pprice];
                }
            }

            if ($product_error) {
                $error = 'Invalid product data. Name: letters only, Qty: positive integer, Price: number like 12.5';
            } elseif (empty($valid_products)) {
                $error = 'At least one product required with all fields filled.';
            } else {
                // array_column() + array_unique() to check duplicate names
                $unique_names = array_column($valid_products, 0);
                if (count($unique_names) !== count(array_unique($unique_names))) {
                    $error = 'Product names must be unique.';
                }
            }

            // ================================
            // 3.3 SAVE VALIDATED DATA TO DATABASE
            // ================================
            if (!$error) {
                if ($action == 'create') {
                    // CREATE - mysqli_prepare() for prepared statement (SQL injection safe)
                    $stmt = mysqli_prepare($conn, "INSERT INTO main_forms (name, description, zipcode, country_id, city_id, state_id) VALUES (?, ?, ?, ?, ?, ?)");
                    // mysqli_stmt_bind_param() - binds variables to ? placeholders ("sssiii" = 3 strings, 3 integers)
                    mysqli_stmt_bind_param($stmt, "sssiii", $name, $description, $zipcode, $country_id, $city_id, $state_id);
                    mysqli_stmt_execute($stmt);  // Execute insert
                    $form_id = mysqli_insert_id($conn);  // Get auto-increment ID

                    // Add products for this form
                    $stmt = mysqli_prepare($conn, "INSERT INTO form_products (form_id, name, qty, price) VALUES (?, ?, ?, ?)");
                    // "isid" = integer(form_id), string(name), integer(qty), double(price)
                    foreach ($valid_products as $prod) {
                        mysqli_stmt_bind_param($stmt, "isid", $form_id, $prod[0], $prod[1], $prod[2]);
                        mysqli_stmt_execute($stmt);
                    }
                    $message = 'Record created successfully!';

                } elseif ($action == 'update') {
                    $id = $_POST['id'] ?? 0;
                    if ($id) {
                        // UPDATE - First delete old products (ON DELETE CASCADE not used here)
                        $stmt = mysqli_prepare($conn, "DELETE FROM form_products WHERE form_id = ?");
                        mysqli_stmt_bind_param($stmt, "i", $id);
                        mysqli_stmt_execute($stmt);

                        // Update main form record
                        $stmt = mysqli_prepare($conn, "UPDATE main_forms SET name=?, description=?, zipcode=?, country_id=?, city_id=?, state_id=? WHERE id=?");
                        mysqli_stmt_bind_param($stmt, "sssiiii", $name, $description, $zipcode, $country_id, $city_id, $state_id, $id);
                        mysqli_stmt_execute($stmt);

                        // Add updated products
                        $stmt = mysqli_prepare($conn, "INSERT INTO form_products (form_id, name, qty, price) VALUES (?, ?, ?, ?)");
                        foreach ($valid_products as $prod) {
                            mysqli_stmt_bind_param($stmt, "isid", $id, $prod[0], $prod[1], $prod[2]);
                            mysqli_stmt_execute($stmt);
                        }
                        $message = 'Record updated successfully!';
                    }
                }
            }
        }
    } elseif ($action == 'delete') {
        // DELETE operation
        $id = $_POST['id'] ?? 0;
        if ($id) {
            $stmt = mysqli_prepare($conn, "DELETE FROM main_forms WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $message = 'Record deleted successfully!';
        }
    }
}

// ======================================
// 4. FETCH DATA FOR DISPLAY (Records table + Dropdown options)
// ======================================
$result = mysqli_query($conn, "SELECT mf.*, c.name as country, ci.name as city, s.name as state FROM main_forms mf 
                     JOIN countries c ON mf.country_id = c.id 
                     JOIN cities ci ON mf.city_id = ci.id 
                     JOIN states s ON mf.state_id = s.id ORDER BY mf.id DESC");
// mysqli_fetch_assoc() - fetch associative array from result
$records = [];
while ($row = mysqli_fetch_assoc($result)) {
    $records[] = $row;
}

// Fetch dropdown data using mysqli_query()
$result = mysqli_query($conn, "SELECT * FROM countries ORDER BY name");
$countries = [];
while ($row = mysqli_fetch_assoc($result)) {
    $countries[] = $row;
}

$result = mysqli_query($conn, "SELECT * FROM cities ORDER BY name");
$cities = [];
while ($row = mysqli_fetch_assoc($result)) {
    $cities[] = $row;
}

$result = mysqli_query($conn, "SELECT * FROM states ORDER BY name");
$states = [];
while ($row = mysqli_fetch_assoc($result)) {
    $states[] = $row;
}

// ======================================
// 5. EDIT MODE LOGIC - Load data only if NOT POST (prevents form repopulation after submit)
// ======================================
if (!$_POST && isset($_GET['edit'])) {
    $id = $_GET['edit'];
    // Load main form data using prepared statement
    $stmt = mysqli_prepare($conn, "SELECT * FROM main_forms WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    // mysqli_stmt_get_result() - gets result set from prepared statement
    $edit_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;

    // Load related products
    $stmt = mysqli_prepare($conn, "SELECT * FROM form_products WHERE form_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $edit_products[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Form - Complete CRUD System</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 0 auto; padding: 20px; }
        .error { color: red; background: #ffe6e6; padding: 10px; border-radius: 5px; }
        .success { color: green; background: #e6ffe6; padding: 10px; border-radius: 5px; }
        .form-group { margin: 10px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 20px; margin: 10px 5px 0 0; cursor: pointer; border: none; border-radius: 4px; }
        button[type="submit"] { background: #007cba; color: white; }
        button[type="button"] { background: #6c757d; color: white; }
        .product-row { border: 1px solid #ccc; padding: 10px; margin: 10px 0; border-radius: 4px; background: #f8f9fa; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f1f1f1; font-weight: bold; }
        a { color: #007cba; text-decoration: none; }
    </style>
</head>
<body>
    <h1>📦 Product Management Form (Complete CRUD)</h1>

    <!-- DISPLAY MESSAGES -->
    <?php if ($message): ?>
        <div class="success">✅ <?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- MAIN FORM -->
    <form method="POST">
        <?php if ($edit_data): ?>
            <!-- EDIT MODE - Hidden fields to identify record -->
            <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
            <input type="hidden" name="action" value="update">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>

        <!-- FORM FIELDS WITH VALIDATION LABELS -->
        <div class="form-group">
            <label>Name (min 3 letters, no numbers/special chars):</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($edit_data['name'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label>Description (min 5 characters):</label>
            <textarea name="description" required><?php echo htmlspecialchars($edit_data['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Zipcode (exactly 6 digits only):</label>
            <input type="text" name="zipcode" maxlength="6" value="<?php echo htmlspecialchars($edit_data['zipcode'] ?? ''); ?>" required>
        </div>

        <!-- CASCADING LOCATION DROPDOWNS -->
        <div class="form-group">
            <label>Country:</label>
            <select name="country_id" id="country" onchange="loadCities()" required>
                <option value="">Select Country</option>
                <?php foreach ($countries as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($edit_data && $edit_data['country_id'] == $c['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>City (depends on Country):</label>
            <select name="city_id" id="city" onchange="loadStates()" required>
                <option value="">Select City</option>
                <?php if ($edit_data): ?>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($edit_data['city_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label>State (depends on City):</label>
            <select name="state_id" id="state" required>
                <option value="">Select State</option>
                <?php if ($edit_data): ?>
                    <?php foreach ($states as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ($edit_data['state_id'] == $s['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <!-- DYNAMIC PRODUCTS SECTION -->
        <h3>🛒 Products (Add multiple, validate all fields, unique names)</h3>
        <button type="button" onclick="addProduct()">➕ Add Product Field</button>
        <div id="products">
            <?php foreach ($edit_products as $i => $p): ?>
                <div class="product-row">
                    <input type="text" name="products[<?php echo $i; ?>][name]" placeholder="Name (letters only)" value="<?php echo htmlspecialchars($p['name']); ?>">
                    <input type="text" name="products[<?php echo $i; ?>][qty]" placeholder="Qty (1,2,3...)" value="<?php echo $p['qty']; ?>">
                    <input type="text" name="products[<?php echo $i; ?>][price]" placeholder="Price (12.5)" value="<?php echo $p['price']; ?>">
                    <button type="button" onclick="removeRow(this)">❌ Remove</button>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- SUBMIT / CANCEL BUTTONS -->
        <button type="submit">💾 Submit</button>
        <?php if ($edit_data): ?>
            <a href="index.php"><button type="button">❌ Cancel</button></a>
        <?php endif; ?>
    </form>

    <!-- RECORDS DISPLAY TABLE -->
    <h2>📋 All Records (<?php echo count($records); ?> found)</h2>
    <?php if (empty($records)): ?>
        <p>No records found. Add your first record above!</p>
    <?php else: ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>Zip</th>
                <th>Country</th>
                <th>City</th>
                <th>State</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($records as $rec): ?>
                <tr>
                    <td><?php echo $rec['id']; ?></td>
                    <td><?php echo htmlspecialchars($rec['name']); ?></td>
                    <td><?php echo htmlspecialchars(substr($rec['description'], 0, 50)); ?>...</td>
                    <td><?php echo htmlspecialchars($rec['zipcode']); ?></td>
                    <td><?php echo htmlspecialchars($rec['country']); ?></td>
                    <td><?php echo htmlspecialchars($rec['city']); ?></td>
                    <td><?php echo htmlspecialchars($rec['state']); ?></td>
                    <td>
                        <a href="?edit=<?php echo $rec['id']; ?>">✏️ Edit</a> |
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this record?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $rec['id']; ?>">
                            <button type="submit">🗑️ Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- JAVASCRIPT FOR INTERACTIVE FEATURES -->
    <script>
        let productIndex = <?php echo count($edit_products); ?>;  // Track dynamic product field index

        // ======================================
        // DYNAMIC PRODUCT ROWS FUNCTIONS
        // ======================================
        
        // Add new product row dynamically
        function addProduct() {
            const div = document.createElement('div');
            div.className = 'product-row';
            div.innerHTML = `
                <input type="text" name="products[${productIndex}][name]" placeholder="Name (letters only)">
                <input type="text" name="products[${productIndex}][qty]" placeholder="Qty (1,2,3...)">
                <input type="text" name="products[${productIndex}][price]" placeholder="Price (12.5)">
                <button type="button" onclick="removeRow(this)">❌ Remove</button>
            `;
            document.getElementById('products').appendChild(div);
            productIndex++;  // Increment for next field
        }

        // Remove specific product row
        function removeRow(btn) {
            btn.parentElement.remove();  // Remove the entire row div
        }

        // ======================================
        // CASCADING DROPDOWNS: Country → City → State
        // Preserves current selection during rebuild
        // ======================================
        
        // Load/filter cities when country changes
        function loadCities() {
            const countryId = document.getElementById('country').value;  // Get selected country
            const citySelect = document.getElementById('city');
            
            // Get current city value to preserve selection
            const currentCityId = citySelect.value;
            
            let html = '<option value="">Select City</option>';
            <?php foreach ($cities as $city): ?>
                // Filter cities by selected country (client-side)
                if (countryId == <?php echo $city['country_id']; ?>) {
                    const selected = currentCityId == <?php echo $city['id']; ?> ? 'selected' : '';
                    html += `<option value="<?php echo $city['id']; ?>" ${selected}>
                        <?php echo addslashes($city['name']); ?></option>`;
                }
            <?php endforeach; ?>
            
            citySelect.innerHTML = html;  // Rebuild options list
            loadStates();  // Chain to load states
        }

        // Load/filter states when city changes  
        function loadStates() {
            const cityId = document.getElementById('city').value;
            const stateSelect = document.getElementById('state');
            
            // Get current state value to preserve selection
            const currentStateId = stateSelect.value;
            
            let html = '<option value="">Select State</option>';
            <?php foreach ($states as $state): ?>
                // Filter states by selected city
                if (cityId == <?php echo $state['city_id']; ?>) {
                    const selected = currentStateId == <?php echo $state['id']; ?> ? 'selected' : '';
                    html += `<option value="<?php echo $state['id']; ?>" ${selected}>
                        <?php echo addslashes($state['name']); ?></option>`;
                }
            <?php endforeach; ?>
            
            stateSelect.innerHTML = html;  // Rebuild options
        }

        // ======================================
        // PAGE LOAD INITIALIZATION
        // ======================================
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($edit_data): ?>
                // On edit mode: initialize cascading dropdowns
                loadCities();
            <?php endif; ?>
        });
    </script>
</body>
</html>

