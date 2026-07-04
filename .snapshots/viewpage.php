<?php
// 1. Secure the page: check if a user is logged in
session_start();
if (!isset($_SESSION['username'])) {
    // If they aren't logged in, send them straight back to the login page
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reubentech Hub - Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { display: flex; min-height: 100vh; background-color: #f1f5f9; color: #1e293b; }
        
        /* Left Navigation Sidebar */
        .sidebar { width: 260px; background-color: #0f172a; color: white; padding: 25px 20px; }
        .sidebar h2 { margin-bottom: 35px; font-size: 20px; color: #38bdf8; text-transform: uppercase; letter-spacing: 1px; }
        .sidebar a { display: block; color: #94a3b8; padding: 12px 15px; text-decoration: none; border-radius: 6px; margin-bottom: 8px; font-weight: 500; }
        .sidebar a:hover, .sidebar a.active { background-color: #1e293b; color: white; }
        
        /* Main Dashboard Content Window */
        .main-content { flex: 1; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .logout-btn { background-color: #ef4444; color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: bold; }
        .logout-btn:hover { background-color: #dc2626; }
        
        /* Banner Area */
        .banner-card { background: white; padding: 20px; border-radius: 12px; margin-bottom: 30px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .banner-card img { max-width: 100%; height: auto; max-height: 180px; border-radius: 8px; }
        
        /* Layout Grid for Columns */
        .market-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card h3 { margin-bottom: 20px; color: #0f172a; font-size: 18px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        
        /* Milk Posting Form Inputs */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; text-align: left; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; }
        .submit-btn { background-color: #0284c7; color: white; border: none; padding: 12px; width: 100%; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .submit-btn:hover { background-color: #0369a1; }
        
        /* Interactive Marketplace Rows */
        .buyer-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 12px; }
        .buyer-details h4 { color: #0f172a; margin-bottom: 4px; text-align: left; }
        .buyer-details p { font-size: 13px; color: #64748b; text-align: left; }
        .price-badge { background-color: #dcfce7; color: #166534; padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 14px; display: inline-block; }
        .sell-btn { background-color: #10b981; color: white; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: bold; display: inline-block; }
        .sell-btn:hover { background-color: #059669; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>Reubentech Hub</h2>
        <a href="#" class="active">Market Dashboard</a>
        <a href="#">My Milk Postings</a>
        <a href="#">Active Buyers</a>
        <a href="#">Transaction History</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <div class="banner-card">
            <img src="milkyway.png" alt="Reubentech Marketplace Banner">
            <p style="margin-top: 12px; color: #64748b; font-weight: 500;">Connecting farm-fresh milk production instantly with local and commercial buyers.</p>
        </div>

        <div class="market-grid">
            
            <div>
                <div class="card">
                    <h3>Live Open Milk Markets (Available Buyers)</h3>
                    
                    <div class="buyer-item">
                        <div class="buyer-details">
                            <h4>Brookside Processing Plant</h4>
                            <p>Demand: 2,000 Liters Needed • Location: Nairobi Industrial Area</p>
                        </div>
                        <div style="text-align: right;">
                            <span class="price-badge">Ksh 45 / Litre</span>
                            <div style="margin-top: 8px;"><a href="#" class="sell-btn">Sell Supply</a></div>
                        </div>
                    </div>

                    <div class="buyer-item">
                        <div class="buyer-details">
                            <h4>New KCC Dairy Cooperative</h4>
                            <p>Demand: 500 Liters Needed • Location: Eldoret Collection Hub</p>
                        </div>
                        <div style="text-align: right;">
                            <span class="price-badge">Ksh 42 / Litre</span>
                            <div style="margin-top: 8px;"><a href="#" class="sell-btn">Sell Supply</a></div>
                        </div>
                    </div>

                    <div class="buyer-item">
                        <div class="buyer-details">
                            <h4>Local Creamery & Bakery</h4>
                            <p>Demand: 120 Liters Needed • Location: Local Market</p>
                        </div>
                        <div style="text-align: right;">
                            <span class="price-badge">Ksh 48 / Litre</span>
                            <div style="margin-top: 8px;"><a href="#" class="sell-btn">Sell Supply</a></div>
                        </div>
                    </div>

                </div>
            </div>

            <div>
                <div class="card">
                    <h3>Post Your Milk for Sale</h3>
                    <form action="post_milk.php" method="POST">
                        <div class="form-group">
                            <label for="liters">Quantity Available (Liters):</label>
                            <input type="number" id="liters" name="liters" placeholder="e.g. 50" required>
                        </div>
                        <div class="form-group">
                            <label for="milk_type">Milk Type:</label>
                            <select id="milk_type" name="milk_type">
                                <option value="Cow">Cow Milk</option>
                                <option value="Goat">Goat Milk</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="asking_price">Your Asking Price per Liter (Ksh):</label>
                            <input type="number" id="asking_price" name="asking_price" placeholder="e.g. 45" required>
                        </div>
                        <button type="submit" class="submit-btn">Publish to Marketplace</button>
                    </form>
                </div>
            </div>

        </div>
    </div>

</body>
</html>