<?php
require_once '../includes/auth.php';
require_once '../includes/ui_settings.php';
requireLogin();

// Redirect admin to admin dashboard
if (isAdmin()) {
    header("Location: ../admin/users.php");
    exit();
}

$active_tab = $_GET['tab'] ?? 'dashboard';
$message = '';
$error = '';

// Ensure commercial buyers exist in DB for clean relational transactions
$commercial_buyers = [
    'brookside_plant' => 'brookside@cooperative.com',
    'new_kcc_coop' => 'kcc@cooperative.com',
    'githunguri_coop' => 'githunguri@cooperative.com'
];
foreach ($commercial_buyers as $b_user => $b_email) {
    try {
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$b_user]);
        if ($chk->rowCount() === 0) {
            $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, 'commercial_placeholder', 'member', 'active')")
                 ->execute([$b_user, $b_email]);
        }
    } catch (PDOException $e) {
        // Table might not be ready, ignore
    }
}

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. Post Milk for Sale
    if ($_POST['action'] === 'post_milk') {
        $liters = floatval($_POST['liters']);
        $milk_type = trim($_POST['milk_type']);
        $price = floatval($_POST['asking_price']);

        if ($liters > 0 && $price > 0) {
            try {
                $stmt = $conn->prepare("INSERT INTO milk_postings (user_id, liters, milk_type, asking_price, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$_SESSION['user_id'], $liters, $milk_type, $price]);
                $message = "Supply posted successfully to the marketplace!";
                $active_tab = 'postings';
            } catch (PDOException $e) {
                $error = "Error posting supply: " . $e->getMessage();
            }
        } else {
            $error = "Please enter valid quantities and prices.";
        }
    }

    // 2. Sell Milk Supply to Commercial Buyer
    if ($_POST['action'] === 'sell_supply') {
        $posting_id = intval($_POST['posting_id']);
        $buyer_username = trim($_POST['buyer_username']);
        $buyer_rate = floatval($_POST['buyer_rate']);

        if ($posting_id > 0 && !empty($buyer_username) && $buyer_rate > 0) {
            try {
                // Fetch posting to verify ownership and status
                $stmt = $conn->prepare("SELECT * FROM milk_postings WHERE id = ? AND user_id = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$posting_id, $_SESSION['user_id']]);
                $posting = $stmt->fetch(PDO::FETCH_OBJ);

                if ($posting) {
                    // Fetch buyer ID
                    $b_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                    $b_stmt->execute([$buyer_username]);
                    $buyer = $b_stmt->fetch(PDO::FETCH_OBJ);

                    if ($buyer) {
                        $conn->beginTransaction();

                        // Mark posting as sold
                        $up_stmt = $conn->prepare("UPDATE milk_postings SET status = 'sold' WHERE id = ?");
                        $up_stmt->execute([$posting_id]);

                        // Calculate total transaction value using buyer's market rate
                        $total_price = $posting->liters * $buyer_rate;

                        // Insert transaction
                        $tx_stmt = $conn->prepare("INSERT INTO transactions (seller_id, buyer_id, posting_id, volume, price, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                        $tx_stmt->execute([$_SESSION['user_id'], $buyer->id, $posting_id, $posting->liters, $total_price]);

                        // Create notification for admin
                        $notif_msg = "Member " . $_SESSION['username'] . " sold " . $posting->liters . "L of " . $posting->milk_type . " milk to " . ucwords(str_replace('_', ' ', $buyer_username)) . " for Ksh " . number_format($total_price) . ". Verify payment ledger.";
                        $notif_stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link) VALUES ('info', 'New Sale Confirmed', ?, 'transactions.php')");
                        $notif_stmt->execute([$notif_msg]);

                        // Trigger SMS Alert to configured admin phone number
                        require_once '../includes/sms.php';
                        sendSMSAlert("New Trade: " . $_SESSION['username'] . " sold " . $posting->liters . "L to " . ucwords(str_replace('_', ' ', $buyer_username)) . " for Ksh " . number_format($total_price) . ". Status: Pending.");

                        $conn->commit();
                        $message = "Sale processed! Pending verification by admin.";
                        $active_tab = 'transactions';
                    } else {
                        $error = "Commercial buyer not registered in database.";
                    }
                } else {
                    $error = "Selected supply posting is no longer active or valid.";
                }
            } catch (PDOException $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $error = "Transaction failed: " . $e->getMessage();
            }
        } else {
            $error = "Invalid transaction parameters.";
        }
    }

    // 3. Post Community Advertisement
    if ($_POST['action'] === 'post_ad') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $phone = trim($_POST['phone'] ?? '');
        $image_url = null;

        if (!empty($title) && !empty($description) && preg_match('/^\+[0-9]{7,15}$/', $phone)) {
            
            // Update the user's phone in the database if it's different/new
            try {
                $upd_stmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ?");
                $upd_stmt->execute([$phone, $_SESSION['user_id']]);
                // Update local session/profile object just in case it's used later
                if(isset($user_profile)) {
                    $user_profile->phone = $phone;
                }
            } catch (PDOException $e) { /* ignore error if phone update fails */ }

            // Handle image upload if present
            if (isset($_FILES['ad_image']) && $_FILES['ad_image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['ad_image']['tmp_name'];
                $file_name = time() . '_' . basename($_FILES['ad_image']['name']);
                
                // Ensure target folder exists
                $target_dir = __DIR__ . '/../uploads/';
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                
                $target_file = $target_dir . $file_name;
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $image_url = 'uploads/' . $file_name;
                }
            }

            try {
                $stmt = $conn->prepare("INSERT INTO advertisements (user_id, title, description, image_url) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $title, $description, $image_url]);
                $message = "Advertisement successfully published to the community portal!";
                $active_tab = 'ads';
            } catch (PDOException $e) {
                $error = "Error posting ad: " . $e->getMessage();
            }
        } else {
            if (empty($phone) || !preg_match('/^\+[0-9]{7,15}$/', $phone)) {
                $error = "A valid phone number with country code is required (e.g. +254...).";
            } else {
                $error = "Title and Description are required.";
            }
        }
    }

    // 4. Cancel Posting Action
    if ($_POST['action'] === 'cancel_posting') {
        $posting_id = intval($_POST['posting_id']);
        if ($posting_id > 0) {
            try {
                $stmt = $conn->prepare("UPDATE milk_postings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
                $stmt->execute([$posting_id, $_SESSION['user_id']]);
                $message = "Supply posting #$posting_id has been cancelled.";
                $active_tab = 'postings';
            } catch (PDOException $e) {
                $error = "Error cancelling posting: " . $e->getMessage();
            }
        }
    }
    
    // 5. Delete Advertisement
    if ($_POST['action'] === 'delete_ad') {
        $ad_id = intval($_POST['ad_id'] ?? 0);
        if ($ad_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM advertisements WHERE id = ? AND user_id = ?");
                $stmt->execute([$ad_id, $_SESSION['user_id']]);
                // Redirect to ads tab to refresh the list
                header('Location: dashboard.php?tab=ads');
                exit();
            } catch (PDOException $e) {
                $error = "Error deleting advertisement: " . $e->getMessage();
            }
        }
    }

    // 6. Submit Payment Verification Reference
    if ($_POST['action'] === 'submit_payment') {
        $ref = strtoupper(trim($_POST['payment_ref'] ?? ''));
        $amount = floatval($_POST['payment_amount'] ?? 0);
        
        if (!empty($ref) && $amount > 0) {
            try {
                $stmt = $conn->prepare("UPDATE users SET has_paid = 2, payment_ref = ?, payment_amount = ? WHERE id = ?");
                $stmt->execute([$ref, $amount, $_SESSION['user_id']]);
                
                // Add admin notification
                $notif_msg = "Member '" . $_SESSION['username'] . "' submitted payment verification. M-Pesa Code: $ref, Amount: Ksh $amount.";
                $conn->prepare("INSERT INTO notifications (type, title, message, link) VALUES ('warning', 'Pending Payment', ?, 'users.php')")
                     ->execute([$notif_msg]);
                     
                // Send SMS alert to configured recipient
                require_once '../includes/sms.php';
                sendSMSAlert("Payment Submitted: Member '" . $_SESSION['username'] . "' paid Ksh $amount (Ref: $ref). Waiting for admin verification.");
                
                $message = "Payment details submitted successfully! Waiting for administrator verification.";
                $active_tab = 'markets';
            } catch (PDOException $e) {
                $error = "Error submitting payment details: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all details.";
        }
    }

    // 6. Update Profile Contact Details
    if ($_POST['action'] === 'update_profile') {
        $phone = trim($_POST['phone'] ?? '');
        if (!empty($phone)) {
            try {
                $stmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ?");
                $stmt->execute([$phone, $_SESSION['user_id']]);
                $message = "Contact details updated successfully!";
                $active_tab = $_GET['tab'] ?? 'profile';
            } catch (PDOException $e) {
                $error = "Error updating contact details: " . $e->getMessage();
            }
        } else {
            $error = "Phone number cannot be empty.";
        }
    }

    // 7. Request Market Access Route Guidance
    if ($_POST['action'] === 'request_market_access') {
        $market_name = trim($_POST['market_name'] ?? '');
        if (!empty($market_name)) {
            try {
                // Fetch the phone number for notification
                $phone_stmt = $conn->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
                $phone_stmt->execute([$_SESSION['user_id']]);
                $u_phone = $phone_stmt->fetchColumn();

                // Fetch active postings to let admin know the milk type
                $active_p_stmt = $conn->prepare("SELECT milk_type, SUM(liters) as total_liters FROM milk_postings WHERE user_id = ? AND status = 'active' GROUP BY milk_type");
                $active_p_stmt->execute([$_SESSION['user_id']]);
                $milk_details = $active_p_stmt->fetchAll(PDO::FETCH_OBJ);
                
                $milk_info = [];
                foreach ($milk_details as $md) {
                    $milk_info[] = $md->milk_type . " (" . number_format($md->total_liters, 1) . "L)";
                }
                $milk_info_str = count($milk_info) > 0 ? implode(', ', $milk_info) : 'No active postings';

                $notif_msg = "Member '" . $_SESSION['username'] . "' has requested route access to: " . $market_name . ". Posted Milk: [" . $milk_info_str . "]. Contact Details: " . ($u_phone ? $u_phone : 'Not Provided');
                $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, link) VALUES ('info', 'Market Route Access Request', ?, 'users.php')");
                $stmt->execute([$notif_msg]);
                $message = "Access request sent successfully! The administrator has been notified and will contact you to help you reach the market.";
                $active_tab = 'markets';
            } catch (PDOException $e) {
                $error = "Error requesting access: " . $e->getMessage();
            }
        } else {
            $error = "Invalid market request.";
        }
    }

    // 8. Occupy a Vacancy (Apply)
    if ($_POST['action'] === 'occupy_job') {
        $job_id = intval($_POST['job_id']);
        $applicant_email = trim($_POST['applicant_email'] ?? '');
        $applicant_phone = trim($_POST['applicant_phone'] ?? '');

        if ($job_id > 0 && !empty($applicant_email) && !empty($applicant_phone)) {
            if (!preg_match('/@gmail\.com$/i', $applicant_email)) {
                $error = "Email address must end with @gmail.com";
                $active_tab = 'vacancies';
            } elseif (!preg_match('/^\+[0-9]{7,15}$/', $applicant_phone)) {
                $error = "Phone number must start with a country code (e.g., +254) and contain only numbers.";
                $active_tab = 'vacancies';
            } else {
                try {
                    // Check if user already applied
                    $chk = $conn->prepare("SELECT id FROM job_applications WHERE job_id = ? AND user_id = ?");
                    $chk->execute([$job_id, $_SESSION['user_id']]);
                    if ($chk->rowCount() > 0) {
                        $error = "You have already applied for this vacancy!";
                    } else {
                        $stmt = $conn->prepare("INSERT INTO job_applications (job_id, user_id, email, phone) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$job_id, $_SESSION['user_id'], $applicant_email, $applicant_phone]);
                        $message = "You have successfully applied for the vacancy! The administrator will review all applications and contact the chosen candidate.";
                    }
                    $active_tab = 'vacancies';
                } catch (PDOException $e) {
                    $error = "Error applying for vacancy: " . $e->getMessage();
                }
            }
        } else {
            $error = "Please provide both email and phone number to apply.";
            $active_tab = 'vacancies';
        }
    }
}

// --- FETCH DATA FOR DASHBOARD VIEW ---
try {
    // 1. Fetch user's postings
    $stmt = $conn->prepare("SELECT * FROM milk_postings WHERE user_id = ? ORDER BY posted_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $my_postings = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    // Calculate stats
    $total_volume = 0;
    $total_value = 0;
    $active_postings = 0;
    $sold_volume = 0;
    $sold_earnings = 0;
    
    foreach ($my_postings as $p) {
        if ($p->status === 'active') {
            $total_volume += $p->liters;
            $total_value += ($p->liters * $p->asking_price);
            $active_postings++;
        } elseif ($p->status === 'sold') {
            $sold_volume += $p->liters;
        }
    }
    
    $avg_price = $total_volume > 0 ? ($total_value / $total_volume) : 0;

    // 2. Fetch user's transaction ledger
    $tx_stmt = $conn->prepare("
        SELECT t.*, u.username as buyer_name, mp.milk_type
        FROM transactions t
        JOIN users u ON t.buyer_id = u.id
        JOIN milk_postings mp ON t.posting_id = mp.id
        WHERE t.seller_id = ?
        ORDER BY t.transaction_date DESC
    ");
    $tx_stmt->execute([$_SESSION['user_id']]);
    $my_transactions = $tx_stmt->fetchAll(PDO::FETCH_OBJ);

    foreach ($my_transactions as $tx) {
        if ($tx->status === 'completed') {
            $sold_earnings += $tx->price;
        }
    }

    // 3. Fetch community advertisements posted by this user
    $ad_stmt = $conn->prepare("SELECT * FROM advertisements WHERE user_id = ? ORDER BY created_at DESC");
    $ad_stmt->execute([$_SESSION['user_id']]);
    $my_ads = $ad_stmt->fetchAll(PDO::FETCH_OBJ);

    // 4. Fetch user details for profile tab
    $user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user_profile = $user_stmt->fetch(PDO::FETCH_OBJ);
    
    // 5. Fetch active vacancies (open jobs)
    try {
        $vacancies = $conn->query("SELECT * FROM jobs WHERE status = 'open' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_OBJ);
    } catch (PDOException $e) {
        $vacancies = [];
    }

} catch (PDOException $e) {
    $my_postings = [];
    $my_transactions = [];
    $my_ads = [];
    $user_profile = null;
    $total_volume = 0;
    $avg_price = 0;
    $sold_earnings = 0;
    $sold_volume = 0;
}

// Load dynamic UI labels from settings table
$volume_unit     = getVolumeUnit($conn);
$currency_symbol = getCurrencySymbol($conn);

// Commercial buyers list
$markets = [
    [
        'username' => 'brookside_plant',
        'name' => 'Brookside Processing Plant',
        'demand' => '2,000 Liters Needed',
        'location' => 'Nairobi Industrial Area',
        'rate' => 45.00,
        'milk_type' => 'Cow'
    ],
    [
        'username' => 'new_kcc_coop',
        'name' => 'New KCC Dairy Cooperative',
        'demand' => '500 Liters Needed',
        'location' => 'Eldoret Collection Hub',
        'rate' => 42.00,
        'milk_type' => 'Cow'
    ],
    [
        'username' => 'githunguri_coop',
        'name' => 'Githunguri Dairy Farmers',
        'demand' => '1,200 Liters Needed',
        'location' => 'Kiambu Area',
        'rate' => 44.00,
        'milk_type' => 'Cow'
    ]
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reubentech Hub - Member Dashboard</title>
  <link rel="stylesheet" href="../css/dashboard.css">
  <link rel="stylesheet" href="../css/components.css">
  <style>
      .badge-info { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
      .badge-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
      .badge-warning { background: #fffde7; color: #854d0e; border: 1px solid #fef08a; }
      .badge-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
      .tab-hidden { display: none !important; }
      .sell-modal {
          position: fixed;
          inset: 0;
          background: rgba(15, 23, 42, 0.5);
          backdrop-filter: blur(4px);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 1000;
      }
      .sell-modal-card {
          background: white;
          padding: 2rem;
          border-radius: var(--radius-lg);
          max-width: 500px;
          width: 100%;
          box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
          border: 1px solid var(--border);
      }
      .nav-links a { color: inherit; text-decoration: none; }

      /* Multi-method Payment Selector Tabs */
      .payment-tab-container {
          margin-top: 1.5rem;
          background: var(--surface);
          border-radius: 12px;
          border: 1px solid var(--border);
          box-shadow: 0 4px 15px rgba(0,0,0,0.03);
          overflow: hidden;
          text-align: left;
      }
      .payment-tabs {
          display: flex;
          background: var(--surface-hover);
          border-bottom: 1px solid var(--border);
      }
      .payment-tab {
          flex: 1;
          padding: 1rem;
          text-align: center;
          font-weight: 600;
          font-size: 0.9rem;
          color: var(--text-muted);
          cursor: pointer;
          border-bottom: 2px solid transparent;
          transition: all 0.2s ease;
          background: transparent;
          border: none;
          outline: none;
      }
      .payment-tab:hover {
          color: var(--text-main);
          background: rgba(0,0,0,0.02);
      }
      .payment-tab.active {
          color: #006837;
          border-bottom-color: #006837;
          background: var(--surface);
      }
      .payment-panel {
          padding: 1.5rem;
          display: none;
      }
      .payment-panel.active {
          display: block;
      }
      
      /* Glassmorphic Phone Simulator for STK Push Demo */
      .phone-simulator-overlay {
          position: fixed;
          inset: 0;
          background: rgba(15, 23, 42, 0.7);
          backdrop-filter: blur(8px);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 2000;
      }
      .phone-device {
          width: 320px;
          height: 580px;
          background: #101012;
          border-radius: 36px;
          border: 10px solid #282830;
          box-shadow: 0 25px 60px -10px rgba(0,0,0,0.7), inset 0 2px 4px rgba(255,255,255,0.15);
          position: relative;
          padding: 10px;
          display: flex;
          flex-direction: column;
      }
      .phone-notch {
          width: 120px;
          height: 20px;
          background: #282830;
          position: absolute;
          top: 8px;
          left: 50%;
          transform: translateX(-50%);
          border-bottom-left-radius: 12px;
          border-bottom-right-radius: 12px;
          z-index: 20;
      }
      .phone-screen {
          flex: 1;
          background: #f1f5f9;
          border-radius: 26px;
          overflow: hidden;
          position: relative;
          display: flex;
          flex-direction: column;
          font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
      }
      .phone-header-bar {
          height: 38px;
          background: #006837;
          color: white;
          padding: 14px 18px 0 18px;
          display: flex;
          justify-content: space-between;
          font-size: 0.7rem;
          font-weight: 600;
          z-index: 10;
      }
      .phone-body {
          flex: 1;
          padding: 1rem 0.75rem 0.5rem 0.75rem;
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          position: relative;
      }
      .stk-popup {
          background: #ffffff;
          border-radius: 14px;
          box-shadow: 0 8px 20px rgba(0,0,0,0.12);
          border: 1px solid #e2e8f0;
          padding: 1.15rem;
          text-align: center;
          margin-top: 1.5rem;
          animation: stkSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      }
      @keyframes stkSlideUp {
          from { transform: translateY(40px); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
      }
      .stk-logo-header {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 0.4rem;
          color: #006837;
          font-weight: 800;
          font-size: 1rem;
          border-bottom: 1px solid #e2e8f0;
          padding-bottom: 0.6rem;
          margin-bottom: 0.75rem;
      }
      .stk-details {
          font-size: 0.8rem;
          color: #334155;
          margin-bottom: 1rem;
          line-height: 1.45;
      }
      .stk-pin-display {
          display: flex;
          justify-content: center;
          gap: 0.85rem;
          margin: 0.75rem 0;
      }
      .stk-dot {
          width: 12px;
          height: 12px;
          border: 2px solid #cbd5e1;
          border-radius: 50%;
          transition: all 0.1s ease;
      }
      .stk-dot.filled {
          background: #006837;
          border-color: #006837;
          transform: scale(1.15);
      }
      .phone-keyboard {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 0.5rem 1rem;
          padding: 0.75rem 1rem;
          background: #ffffff;
          border-top-left-radius: 20px;
          border-top-right-radius: 20px;
          box-shadow: 0 -3px 10px rgba(0,0,0,0.02);
      }
      .key-btn {
          height: 42px;
          border-radius: 8px;
          background: #f8fafc;
          border: 1px solid #e2e8f0;
          font-size: 1.15rem;
          font-weight: 700;
          color: #334155;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          user-select: none;
          transition: all 0.1s;
      }
      .key-btn:active {
          background: #cbd5e1;
          transform: scale(0.95);
      }
      .key-btn.action {
          font-size: 0.75rem;
          font-weight: 600;
          background: #f1f5f9;
          color: #64748b;
      }
      .key-btn.submit {
          background: #006837;
          color: white;
          border-color: #006837;
      }
      .key-btn.submit:active {
          background: #004d28;
      }
      .phone-home-line {
          width: 100px;
          height: 4px;
          background: #cbd5e1;
          border-radius: 2px;
          margin: 6px auto 2px auto;
      }
      @keyframes stkSpin {
          to { transform: rotate(360deg); }
      }

      /* Manual Support Section Style Classes */
      .support-heading {
          font-size: 1rem;
          font-weight: 700;
          color: var(--text-dark);
          margin-bottom: 0.5rem;
      }
      .support-text {
          color: var(--text-muted);
          margin-bottom: 1rem;
          font-size: 0.85rem;
          line-height: 1.4;
      }
      .support-buttons {
          display: flex;
          flex-direction: column;
          gap: 0.75rem;
      }
      .support-btn-whatsapp {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 0.5rem;
          background: #25D366;
          color: white;
          border: none;
          padding: 0.75rem 1rem;
          font-weight: bold;
          font-size: 1rem;
          border-radius: 8px;
          text-decoration: none;
          width: 100%;
          transition: opacity 0.2s, transform 0.1s;
      }
      .support-btn-whatsapp:hover {
          opacity: 0.9;
      }
      .support-btn-whatsapp:active {
          transform: scale(0.98);
      }
      .support-btn-call {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 0.5rem;
          border: 1px solid #006837;
          color: #006837;
          background: transparent;
          padding: 0.75rem 1rem;
          font-weight: bold;
          font-size: 1rem;
          border-radius: 8px;
          text-decoration: none;
          width: 100%;
          transition: background-color 0.2s, transform 0.1s;
      }
      .support-btn-call:hover {
          background-color: rgba(0, 104, 55, 0.05);
      }
      .support-btn-call:active {
          transform: scale(0.98);
      }
  </style>
</head>
<body>
  <div class="app-wrapper">
    <div class="app-frame">
      <div id="sidebar-overlay" class="sidebar-overlay"></div>

      <!-- SIDEBAR -->
      <aside id="sidebar" class="sidebar" aria-label="Primary navigation">
        <div class="sidebar-header">
          <div class="sidebar-logo">
            <div class="sidebar-logo-icon">RH</div>
            <span class="sidebar-logo-text">Reubentech Hub</span>
          </div>
          <button id="sidebar-collapse" class="sidebar-toggle" aria-label="Collapse sidebar">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m17 18-6-6 6-6"/><path d="M7 6v12"/></svg>
          </button>
        </div>
        <nav class="sidebar-nav">
          <ul>
            <!-- 1. Dashboard -->
            <li><a href="?tab=dashboard" class="sidebar-link <?= $active_tab==='dashboard'?'active':'' ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
              <span class="sidebar-link-text">Overview Dashboard</span></a>
            </li>
            
            <!-- 2. Markets -->
            <li><a href="?tab=markets" class="sidebar-link <?= $active_tab==='markets'?'active':'' ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/></svg>
              <span class="sidebar-link-text">Buyers & Markets</span></a>
            </li>

            <!-- 3. Post Supply -->
            <li><a href="?tab=post_supply" class="sidebar-link <?= $active_tab==='post_supply'?'active':'' ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              <span class="sidebar-link-text">Post Milk Supply</span></a>
            </li>

            <!-- 4. My Postings -->
            <li><a href="?tab=postings" class="sidebar-link <?= $active_tab==='postings'?'active':'' ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              <span class="sidebar-link-text">My Supply Postings</span></a>
            </li>

            <!-- 5. Transactions -->
            <li><a href="?tab=transactions" class="sidebar-link <?= $active_tab==='transactions'?'active':'' ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
              <span class="sidebar-link-text">Transaction History</span></a>
            </li>

            <!-- 6. Advertisements -->
            <li><a href="?tab=ads" class="sidebar-link <?= $active_tab==='ads'?'active':'' ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/></svg>
              <span class="sidebar-link-text">Community Ads</span></a>
            </li>

            <!-- Vacancies -->
            <li><a href="?tab=vacancies" class="sidebar-link <?= $active_tab==='vacancies'?'active':'' ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
              <span class="sidebar-link-text">Search for Vacancies</span></a>
            </li>

            <!-- 7. Help -->
            <li><a href="?tab=help" class="sidebar-link <?= $active_tab==='help'?'active':'' ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>
              <span class="sidebar-link-text">FAQs & Help Support</span></a>
            </li>

            <!-- 8. Profile -->
            <li><a href="?tab=profile" class="sidebar-link <?= $active_tab==='profile'?'active':'' ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <span class="sidebar-link-text">My Profile info</span></a>
            </li>

            <!-- 9. Public Site -->
            <li><a href="../index.php" class="sidebar-link">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
              <span class="sidebar-link-text">Return to Public Site</span></a>
            </li>
          </ul>
        </nav>
      </aside>

      <!-- MAIN -->
      <main class="main-content">
        <header class="top-header">
          <div class="top-header-inner">
            <button id="mobile-menu-btn" class="mobile-menu-btn" aria-label="Open menu">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
            </button>
            
            <div class="search-wrapper">
              <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <input type="text" class="search-input" placeholder="Search postings or logs..." aria-label="Search">
            </div>

            <div class="header-actions">
              <button class="icon-btn" data-action="toggle-theme" aria-label="Toggle theme">
                <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
              </button>
              <div class="flex items-center gap-2 ml-2 pl-4" style="border-left: 1px solid var(--border);">
                <div class="avatar"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div>
                <div class="text-sm font-semibold hidden-mobile"><?= htmlspecialchars($_SESSION['username']) ?></div>
                <a href="../logout.php" class="btn btn-outline" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">Logout</a>
              </div>
            </div>
          </div>
        </header>

        <div class="content-container">
          <!-- Welcome and Alert section -->
          <div class="flex items-center justify-between">
            <div>
              <h1 class="font-bold">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>! 👋</h1>
              <p class="text-muted text-sm mt-1">Manage your dairy farm listings and transact with buyers.</p>
            </div>
          </div>

          <?php if($message): ?>
            <div style="background:var(--success-bg); color:var(--success); padding:1rem; border-radius:var(--radius-md); font-weight:bold; margin: 1rem 0; border: 1px solid var(--success);">
                ✅ <?= htmlspecialchars($message) ?>
            </div>
          <?php endif; ?>
          <?php if($error): ?>
            <div style="background:#fef2f2; color:#b91c1c; padding:1rem; border-radius:var(--radius-md); font-weight:bold; margin: 1rem 0; border: 1px solid #fee2e2;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
          <?php endif; ?>

          <!-- ==================== TAB 1: OVERVIEW DASHBOARD ==================== -->
          <div class="<?= $active_tab !== 'dashboard' ? 'tab-hidden' : '' ?>">
              <?php if(empty($user_profile->phone)): ?>
                <div class="card" style="margin-top: 1.5rem; border-top: 4px solid #ca8a04; background: #fef9c3; padding: 1.5rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); text-align: left;">
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom: 0.75rem;">
                        <span style="font-size: 1.5rem;">📱</span>
                        <h3 style="color:#854d0e; font-size: 1.1rem; margin:0; font-family:var(--font-heading);">Register Your Phone Number</h3>
                    </div>
                    <p style="color:#713f12; font-size: 0.875rem; line-height: 1.5; margin-bottom: 1rem;">
                        Please register your mobile phone number below so the cooperative administration can contact you for milk delivery coordination, route guidance, and status alerts.
                    </p>
                    <form action="dashboard.php?tab=dashboard" method="POST" style="max-width: 450px; width: 100%;">
                        <input type="hidden" name="action" value="update_profile">
                        <div style="display: flex; gap: 0.5rem; width: 100%;">
                            <span style="background: var(--surface-hover); border: 1px solid var(--border); padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-weight: 600; color: var(--text-muted); font-size: 0.875rem; display: flex; align-items: center;">+254</span>
                            <input class="form-input" type="tel" name="phone" placeholder="e.g. 0799031535" pattern="[0-9]{9,10}" required style="flex:1; border-color:#ca8a04; background:#fff;">
                            <button type="submit" class="btn" style="background:#ca8a04; color:white; font-weight:bold; padding:0.75rem 1.25rem; border-radius:var(--radius-sm); border:none; cursor:pointer; transition:background 0.2s;" onmouseover="this.style.background='#a16207'" onmouseout="this.style.background='#ca8a04'">Register Number</button>
                        </div>
                        <small style="color:#713f12; display:block; margin-top:0.25rem; font-size:0.75rem;">Format: 07XXXXXXXX or 01XXXXXXXX (e.g., 0799031535)</small>
                    </form>
                </div>
              <?php endif; ?>

              <!-- Stats Row -->
              <div class="stats-grid" style="margin-top:1.5rem;">
                <div class="stat-card">
                  <div class="stat-card-label">Active Supply Volume</div>
                  <div class="stat-card-value"><?= number_format($total_volume) ?> <span class="text-sm text-muted"><?= htmlspecialchars($volume_unit) ?></span></div>
                </div>
                <div class="stat-card">
                  <div class="stat-card-label">Avg Asking Price</div>
                  <div class="stat-card-value"><span class="text-sm text-muted"><?= htmlspecialchars($currency_symbol) ?></span> <?= number_format($avg_price, 2) ?></div>
                </div>
                <div class="stat-card">
                  <div class="stat-card-label">Total Verified Earnings</div>
                  <div class="stat-card-value"><span class="text-sm text-muted"><?= htmlspecialchars($currency_symbol) ?></span> <?= number_format($sold_earnings) ?></div>
                </div>
              </div>

              <!-- Dashboard Grid -->
              <div class="dashboard-grid">
                <!-- Left Column: Summary tables -->
                <div class="space-y-6">
                  <div class="card">
                    <div class="card-header">
                        <h3>Overview</h3>
                    </div>
                    <div style="padding: 1.5rem;">
                        <p style="color:var(--text-muted); line-height: 1.6;">
                            Welcome to the Reubentech Hub dairy cooperative portal! Use the sidebar tabs to complete your daily activities:
                        </p>
                        <ul style="margin-top: 1rem; margin-left: 1.5rem; color:var(--text-muted); line-height: 1.8;">
                            <li><strong>Buyers & Markets:</strong> Instantly trade supply with corporate milk processing plants.</li>
                            <li><strong>Post Milk Supply:</strong> Form to list your new cow, goat, or camel milk quantity.</li>
                            <li><strong>My Supply Postings:</strong> View, review, or cancel your active sale listings.</li>
                            <li><strong>Transaction History:</strong> Print receipt invoices or track pending verifications.</li>
                            <li><strong>Community Ads:</strong> Share community posts or sell equipment to members.</li>
                        </ul>
                    </div>
                  </div>
                </div>

                <!-- Right Column: Quick Profile Summary -->
                <div class="space-y-6">
                  <div class="card">
                    <div class="card-header"><h3>Account Status</h3></div>
                    <div style="padding: 1.5rem; text-align: center;">
                        <div class="avatar" style="width:70px; height:70px; font-size:2rem; margin:0 auto 1rem auto;"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div>
                        <h4 class="font-bold" style="font-size:1.25rem;"><?= htmlspecialchars($_SESSION['username']) ?></h4>
                        <p style="color:var(--text-muted); font-size:0.875rem; margin-top:0.25rem;">Role: Member (Dairy Producer)</p>
                        <div style="margin-top:1.25rem;">
                            <span class="badge badge-success">Account Active</span>
                        </div>
                    </div>
                  </div>
                </div>
              </div>
          </div>

          <!-- ==================== TAB 2: MARKETS ==================== -->
          <div class="<?= $active_tab !== 'markets' ? 'tab-hidden' : '' ?>">
              
              <!-- User Active Supply Listings Section -->
              <div class="card" style="margin-top: 1.5rem; border-top: 4px solid var(--color-primary);">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 1rem;">
                  <h3 style="font-family: var(--font-heading); font-weight: 700;">Your Active Milk Supply Listings</h3>
                  <a href="?tab=post_supply" class="btn btn-brand" style="font-size:0.8rem; padding:0.4rem 1rem;">+ Post New Supply</a>
                </div>
                <div style="padding: 1.5rem;">
                  <?php 
                  $active_postings_list = array_filter($my_postings, function($p) { return $p->status === 'active'; });
                  if (count($active_postings_list) > 0): 
                  ?>
                  <div class="data-table-wrapper">
                    <table class="data-table">
                      <thead>
                        <tr>
                          <th>Posting ID</th>
                          <th>Milk Type</th>
                          <th>Volume (<?= htmlspecialchars($volume_unit) ?>)</th>
                          <th>Your Asking Price</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach($active_postings_list as $p): ?>
                        <tr>
                          <td>#<?= str_pad($p->id, 5, '0', STR_PAD_LEFT) ?></td>
                          <td><?= htmlspecialchars($p->milk_type) ?></td>
                          <td class="font-semibold"><?= htmlspecialchars($p->liters) ?> <?= htmlspecialchars($volume_unit) ?></td>
                          <td><?= htmlspecialchars($currency_symbol) ?> <?= htmlspecialchars($p->asking_price) ?> / <?= htmlspecialchars($volume_unit) ?></td>
                          <td><span class="badge badge-info">Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <?php else: ?>
                    <p class="text-muted text-center" style="padding: 1rem 0; font-size: 0.95rem;">
                      You have no active milk supply postings listed. 
                      <a href="?tab=post_supply" style="color: var(--color-primary); font-weight: 600; text-decoration: underline;">Post a milk supply here</a> to start selling.
                    </p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="card" style="margin-top: 1.5rem;">
                <div class="card-header">
                  <h3>Live Commercial Open Milk Markets</h3>
                </div>
                <p class="text-muted text-sm" style="padding: 0 1.5rem 1rem 1.5rem;">
                    Select an open corporate buyer demand rate to sell your active supply listings.
                </p>
                <div class="buyer-list" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                  <?php foreach($markets as $market): ?>
                  <div class="buyer-item" style="border: 1px solid var(--border); padding: 1.5rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
                    <div class="buyer-info">
                      <h4 class="font-bold" style="font-size:1.1rem; color:var(--text-dark);"><?= $market['name'] ?></h4>
                      <p class="text-muted text-sm" style="margin-top:0.25rem;">Demand: <?= $market['demand'] ?></p>
                      <?php if (($user_profile->has_paid ?? 0) == 1): ?>
                        <p class="text-muted text-sm" style="margin-top:0.25rem;">• Location: <?= htmlspecialchars($market['location']) ?></p>
                      <?php else: ?>
                        <p style="margin-top:0.25rem; font-size:0.85rem; color:#b91c1c; font-weight:600;">
                          📍 Location: 🔒 Restricted (Contact Admin at 0799031535 for route access)
                        </p>
                      <?php endif; ?>
                    </div>
                    <div class="buyer-action" style="text-align: right;">
                      <div class="badge badge-success" style="font-size:0.9rem; padding:0.4rem 0.8rem; font-weight:bold;">Buying Rate: <?= htmlspecialchars($currency_symbol) ?> <?= number_format($market['rate'], 2) ?> / <?= htmlspecialchars($volume_unit) ?></div>
                      <div style="margin-top: 0.75rem;">
                        <?php if (($user_profile->has_paid ?? 0) == 1): ?>
                          <button class="btn btn-brand" onclick="openSellModal('<?= $market['username'] ?>', '<?= $market['name'] ?>', <?= $market['rate'] ?>)" style="padding: 0.4rem 1rem; font-size: 0.8rem;">🤝 Sell Milk to Buyer</button>
                        <?php else: ?>
                          <form method="POST" action="dashboard.php?tab=markets">
                            <input type="hidden" name="action" value="request_market_access">
                            <input type="hidden" name="market_name" value="<?= htmlspecialchars($market['name']) ?>">
                            <button type="submit" class="btn btn-outline" style="padding: 0.4rem 1rem; font-size: 0.8rem; border-color:#0284c7; color:#0284c7;">🔑 Request Access Approval</button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>

                <?php if (($user_profile->has_paid ?? 0) != 1): ?>
                <div style="border-top: 1px solid var(--border); padding-top: 2rem;">
                  <div class="card-header" style="text-align:center; border-bottom: 1px solid var(--border); padding-bottom: 1rem;">
                    <?php if (($user_profile->has_paid ?? 0) == 2): ?>
                      <h3 style="color:#854d0e;">⏳ Payment Verification Pending</h3>
                    <?php else: ?>
                      <h3 style="color:#b91c1c;">🔒 Live Markets Access Restricted</h3>
                    <?php endif; ?>
                  </div>
                  
                  <div style="padding: 2rem 1.5rem; max-width: 600px; margin: 0 auto; text-align: center;">
                    <?php if (($user_profile->has_paid ?? 0) == 2): ?>
                      <div style="background:#fffde7; color:#854d0e; padding:1.25rem; border-radius:var(--radius-md); font-weight:500; margin-bottom: 2rem; border: 1px solid #fef08a; text-align:center; line-height:1.6;">
                          Your verification payment has been submitted and is currently pending administrator verification. Please contact the administrator at <strong>0799031535</strong> to speed up approval.
                      </div>
                    <?php else: ?>
                      <div style="background:#fef2f2; color:#b91c1c; padding:1.25rem; border-radius:var(--radius-md); font-weight:500; margin-bottom: 2rem; border: 1px solid #fecaca; text-align:center; line-height:1.6;">
                          To protect cooperative trading operations, all members must complete their account verification before accessing the live buyers and selling milk supply.<br>
                          <strong style="display:block; margin-top:0.75rem; font-size:1.1rem; color:#dc2626;">Paybill: 400200 | Account Number: 1115252 (REUBEN MATOKE)</strong>
                      </div>
                    <?php endif; ?>

                    <!-- Automated STK Push Payment Form -->
                    <div style="background:#ffffff; border: 1px solid var(--border); padding: 2rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); margin-bottom: 2rem; text-align: center;">
                        <h4 style="font-size: 1.25rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1rem; font-family: var(--font-heading);">⚡ Pay Account Verification Fee (Ksh 500)</h4>
                        <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem; line-height: 1.5;">
                            Enter your M-Pesa phone number below to initiate a direct STK Push of Ksh 500. After typing your M-Pesa PIN, your account will unlock immediately.
                        </p>
                        
                        <form onsubmit="initiateMpesaPayment(event)" style="max-width: 400px; margin: 0 auto; text-align: left; margin-bottom: 1.5rem;">
                            <div class="form-group" style="margin-bottom: 1.25rem;">
                                <label class="form-label" for="mpesa_phone" style="font-weight: 600;">M-Pesa Phone Number</label>
                                <input class="form-input" type="tel" id="mpesa_phone" placeholder="e.g. 07XXXXXXXX" value="<?= htmlspecialchars($user_profile->phone ?? '') ?>" required>
                            </div>
                            <button type="submit" id="mpesaSubmitBtn" class="btn btn-brand" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; background: #006837; border-color: #006837; color: white;">
                                💸 Pay 500 KES via M-Pesa
                            </button>
                        </form>
                        
                        <hr style="border: 0; border-top: 1px solid var(--border); margin: 1.5rem 0;">
                        
                        <h4 class="support-heading">Need Manual Support?</h4>
                        <p class="support-text">
                            If you paid via Co-op Bank or need help, contact the administrator.
                        </p>
                        <div class="support-buttons">
                            <a href="https://wa.me/254799031535?text=Hello%20Admin,%20I%20need%20my%20dairy%20account%20verified%20and%20unlocked%20for%20live%20markets.%20My%20username%20is:%20<?= urlencode($_SESSION['username']) ?>" class="support-btn-whatsapp" target="_blank">
                                💬 Contact Admin on WhatsApp
                            </a>
                            <a href="tel:0799031535" class="support-btn-call">
                                📞 Call Admin: 0799031535
                            </a>
                        </div>
                    </div>
                  </div>
                </div>
                <?php endif; ?>
              </div>
          </div>

          <!-- ==================== TAB 3: POST SUPPLY ==================== -->
          <div class="<?= $active_tab !== 'post_supply' ? 'tab-hidden' : '' ?>">
              <div class="card" style="margin-top: 1.5rem; max-width: 650px; border-top: 4px solid var(--brand);">
                <div class="card-header"><h3>Post Your Milk for Sale</h3></div>
                <form id="postMilkForm" action="dashboard.php?tab=post_supply" method="POST" style="padding:1.5rem;" onsubmit="handlePostMilkSubmit(event)">
                  <input type="hidden" name="action" value="post_milk">
                  <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label" for="liters">Quantity Available (Liters)</label>
                    <input class="form-input" type="number" step="0.1" id="liters" name="liters" placeholder="e.g. 100" required>
                  </div>
                  <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label" for="milk_type">Milk Type</label>
                    <select class="form-select" id="milk_type" name="milk_type">
                      <!-- Cow Milk Options -->
                      <optgroup label="Cow Milk">
                        <option value="Cow - Raw Whole Milk (Chilled)">Cow - Raw Whole Milk (Chilled)</option>
                        <option value="Cow - Fermented Milk (Maziwa Lala)">Cow - Fermented Milk (Maziwa Lala)</option>
                        <option value="Cow - Pasteurized Packet Milk">Cow - Pasteurized Packet Milk</option>
                        <option value="Cow - UHT Long Life Milk">Cow - UHT Long Life Milk</option>
                      </optgroup>
                      <!-- Goat Milk Options -->
                      <optgroup label="Goat Milk">
                        <option value="Goat - Raw Whole Milk (Chilled)">Goat - Raw Whole Milk (Chilled)</option>
                        <option value="Goat - Fermented Milk (Maziwa Lala)">Goat - Fermented Milk (Maziwa Lala)</option>
                        <option value="Goat - Pasteurized Packet Milk">Goat - Pasteurized Packet Milk</option>
                        <option value="Goat - UHT Long Life Milk">Goat - UHT Long Life Milk</option>
                      </optgroup>
                      <!-- Camel Milk Options -->
                      <optgroup label="Camel Milk">
                        <option value="Camel - Raw Whole Milk (Chilled)">Camel - Raw Whole Milk (Chilled)</option>
                        <option value="Camel - Fermented Milk (Maziwa Lala)">Camel - Fermented Milk (Maziwa Lala)</option>
                        <option value="Camel - Pasteurized Packet Milk">Camel - Pasteurized Packet Milk</option>
                        <option value="Camel - UHT Long Life Milk">Camel - UHT Long Life Milk</option>
                      </optgroup>
                    </select>
                  </div>
                  <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" for="asking_price">Your Asking Price (Ksh / Litre)</label>
                    <div style="position: relative;">
                      <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-weight: 600;">Ksh</span>
                      <input class="form-input" type="number" step="0.5" id="asking_price" name="asking_price" placeholder="45" style="padding-left: 3.25rem;" required>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-brand" style="width: 100%;">Publish to Marketplace</button>
                </form>
              </div>
          </div>

          <!-- ==================== TAB 4: MY POSTINGS ==================== -->
          <div class="<?= $active_tab !== 'postings' ? 'tab-hidden' : '' ?>">
              <div class="card" style="margin-top: 1.5rem;">
                <div class="card-header"><h3>Your Milk Supply Postings Inventory</h3></div>
                <div class="data-table-wrapper">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Posting ID</th>
                        <th>Timestamp</th>
                        <th>Milk Type</th>
                        <th>Volume (L)</th>
                        <th>Asking Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if(count($my_postings) > 0): ?>
                          <?php foreach($my_postings as $p): ?>
                          <tr>
                            <td>#<?= str_pad($p->id, 5, '0', STR_PAD_LEFT) ?></td>
                            <td><?= date('M d Y, g:i A', strtotime($p->posted_at)) ?></td>
                            <td><?= htmlspecialchars($p->milk_type) ?></td>
                            <td class="font-semibold"><?= htmlspecialchars($p->liters) ?> <?= htmlspecialchars($volume_unit) ?></td>
                            <td><?= htmlspecialchars($currency_symbol) ?> <?= htmlspecialchars($p->asking_price) ?>/<?= htmlspecialchars($volume_unit) ?></td>
                            <td>
                                <?php if($p->status === 'active'): ?>
                                    <span class="badge badge-info">Active</span>
                                <?php elseif($p->status === 'sold'): ?>
                                    <span class="badge badge-success">Sold</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><?= ucfirst(htmlspecialchars($p->status)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($p->status === 'active'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this listing?')">
                                    <input type="hidden" name="action" value="cancel_posting">
                                    <input type="hidden" name="posting_id" value="<?= $p->id ?>">
                                    <button type="submit" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color:#dc2626; border-color:#fee2e2;">Cancel</button>
                                </form>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:0.75rem;">None</span>
                                <?php endif; ?>
                            </td>
                          </tr>
                          <?php endforeach; ?>
                      <?php else: ?>
                          <tr><td colspan="7" style="text-align:center;">You have no milk postings listed.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
          </div>

          <!-- ==================== TAB 5: TRANSACTIONS ==================== -->
          <div class="<?= $active_tab !== 'transactions' ? 'tab-hidden' : '' ?>">
              <div class="card" style="margin-top: 1.5rem;">
                <div class="card-header"><h3>Your Financial Sales Ledger</h3></div>
                <div class="data-table-wrapper">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Transaction ID</th>
                        <th>Buyer Account</th>
                        <th>Item Category</th>
                        <th>Volume</th>
                        <th>Total Value</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if(count($my_transactions) > 0): ?>
                          <?php foreach($my_transactions as $tx): ?>
                          <tr>
                            <td><?= date('M d, Y H:i', strtotime($tx->transaction_date)) ?></td>
                            <td>#<?= str_pad($tx->id, 5, '0', STR_PAD_LEFT) ?></td>
                            <td><strong><?= ucwords(str_replace('_', ' ', $tx->buyer_name)) ?></strong></td>
                            <td><?= htmlspecialchars($tx->milk_type) ?> Milk</td>
                            <td><?= htmlspecialchars($tx->volume) ?> <?= htmlspecialchars($volume_unit) ?></td>
                            <td class="font-semibold"><?= htmlspecialchars($currency_symbol) ?> <?= number_format($tx->price, 2) ?></td>
                            <td>
                                <?php if($tx->status === 'completed'): ?>
                                    <span class="badge badge-success">✅ Completed</span>
                                <?php elseif($tx->status === 'pending'): ?>
                                    <span class="badge badge-warning">⏳ Pending Approval</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">❌ Cancelled</span>
                                <?php endif; ?>
                            </td>
                          </tr>
                          <?php endforeach; ?>
                      <?php else: ?>
                          <tr><td colspan="7" style="text-align:center;">No trade transactions recorded yet.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
          </div>

          <!-- ==================== TAB 6: ADVERTISEMENTS ==================== -->
          <div class="<?= $active_tab !== 'ads' ? 'tab-hidden' : '' ?>">
              <div class="dashboard-grid" style="margin-top: 1.5rem;">
                  <!-- Left: Posted Ads -->
                  <div class="card">
                      <div class="card-header"><h3>Public Community Advertisements</h3></div>
                      <div style="padding: 1.5rem; max-height: 500px; overflow-y: auto;" class="space-y-4">
                               <?php
                                   // Fetch all advertisements for public view (no contact info)
                                   $public_ads = $conn->query("SELECT a.*, u.username FROM advertisements a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC")->fetchAll();
                               ?>
                               <?php if(count($public_ads) > 0): ?>
                                   <?php foreach($public_ads as $ad): ?>
                                   <div style="border: 1px solid var(--border); padding: 1rem; border-radius: var(--radius-md); display: flex; gap: 1rem;">
                                       <?php if($ad->image_url): ?>
                                           <img src="../<?php echo htmlspecialchars($ad->image_url); ?>" style="width: 80px; height: 80px; object-fit: cover; border-radius: var(--radius-md);" alt="Ad Image">
                                       <?php else: ?>
                                           <div style="width: 80px; height: 80px; background:#f1f5f9; border-radius: var(--radius-md); display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:1.5rem;">🖼️</div>
                                       <?php endif; ?>
                                       <div>
                                           <h4 class="font-bold"><?php echo htmlspecialchars($ad->title); ?></h4>
                                           <p class="text-sm text-muted" style="margin-top:0.25rem;"><?php echo nl2br(htmlspecialchars($ad->description)); ?></p>
                                           <span class="text-xs text-muted" style="display:block; margin-top:0.5rem;">Posted by: <?php echo htmlspecialchars($ad->username); ?> on <?php echo date('d M Y', strtotime($ad->created_at)); ?></span>
                                           <?php if($ad->user_id == $_SESSION['user_id']): ?>
                        <form method="POST" action="dashboard.php?tab=ads" style="margin-top:0.5rem;" onsubmit="return confirm('Delete this advertisement?');">
                            <input type="hidden" name="action" value="delete_ad">
                            <input type="hidden" name="ad_id" value="<?php echo $ad->id; ?>">
                            <button type="submit" class="btn btn-outline" style="background:#fca5a5; color:#b91c1b; border-color:#b91c1b;">Delete</button>
                        </form>
<?php endif; ?>
                                   </div>
                                   <?php endforeach; ?>
                               <?php else: ?>
                                   <p class="text-muted text-center" style="padding: 2rem 0;">No community advertisements available.</p>
                               <?php endif; ?>
                      </div>
                  </div>

                  <!-- Right: Post Ad form -->
                  <div class="card">
                      <div class="card-header"><h3>Publish a Community Notice / Advertisement</h3></div>
                      <form action="dashboard.php?tab=ads" method="POST" enctype="multipart/form-data" style="padding: 1.5rem;">
                          <input type="hidden" name="action" value="post_ad">
                          <div class="form-group" style="margin-bottom:1rem;">
                              <label class="form-label" for="ad_title">Ad Title</label>
                              <input class="form-input" type="text" id="ad_title" name="title" placeholder="e.g. Premium Chaff Cutter for Sale" required>
                          </div>
                          <div class="form-group" style="margin-bottom:1rem;">
                              <label class="form-label" for="ad_desc">Description / Notice Details</label>
                              <textarea class="form-input" id="ad_desc" name="description" rows="4" placeholder="Describe the item, pricing, and contact information..." style="resize:vertical; font-family:inherit;" required></textarea>
                          </div>
                          <div class="form-group" style="margin-bottom:1rem;">
                              <label class="form-label" for="ad_phone">Contact Phone Number</label>
                              <input class="form-input" type="tel" id="ad_phone" name="phone" value="<?= htmlspecialchars($user_profile->phone ?? '') ?>" required pattern="^\+[0-9]{7,15}$" title="Phone number must start with a + country code followed by numbers only (e.g., +254712345678)" placeholder="e.g. +2547...">
                              <small style="color:var(--text-muted); display:block; margin-top:0.25rem;">Must include country code (e.g., +254). This will be shown on your ad.</small>
                          </div>
                          <div class="form-group" style="margin-bottom:1rem;">
                              <label class="form-label" for="ad_image">Attachment Image <small style="color:var(--text-muted)">(optional)</small></label>
                              <input class="form-input" type="file" id="ad_image" name="ad_image" accept="image/*">
                          </div>
                          <button type="submit" class="btn btn-brand" style="width: 100%; margin-top: 1rem;">Publish Notice</button>
                      </form>
                  </div>
              </div>
          </div>

          <!-- ==================== TAB 7: HELP ==================== -->
          <div class="<?= $active_tab !== 'help' ? 'tab-hidden' : '' ?>">
              <div class="card" style="margin-top: 1.5rem; max-width:800px;">
                  <div class="card-header"><h3>Cooperative Support FAQs</h3></div>
                  <div style="padding:1.5rem;" class="space-y-4">
                      <div style="border-bottom:1px solid var(--border); padding-bottom:1rem;">
                          <h4 class="font-bold" style="color:var(--text-dark);">Q: How do I sell my milk supply to a processor?</h4>
                          <p class="text-sm text-muted" style="margin-top:0.25rem;">
                              A: First, add a posting containing your volume and milk type in the "Post Milk Supply" tab. Once added, navigate to the "Buyers & Markets" tab, find your preferred processor (e.g. Brookside), click "Sell Supply", choose your posting, and submit.
                          </p>
                      </div>
                      <div style="border-bottom:1px solid var(--border); padding-bottom:1rem;">
                          <h4 class="font-bold" style="color:var(--text-dark);">Q: How do my earnings get verified?</h4>
                          <p class="text-sm text-muted" style="margin-top:0.25rem;">
                              A: When you sell to a processor, the transaction is marked as pending. Once physical collection and quality control verify the liters at the collection center, the administrator marks the ledger row as completed, adding the value to your verified earnings.
                          </p>
                      </div>
                      <div>
                          <h4 class="font-bold" style="color:var(--text-dark);">Q: How do I upload an advertisement?</h4>
                          <p class="text-sm text-muted" style="margin-top:0.25rem;">
                              A: Head to the "Community Ads" tab and fill out the publishing form on the right. You can optionally attach a JPEG or PNG image showing the item or equipment. Your post will instantly appear on the public homepage.
                          </p>
                      </div>
                  </div>
              </div>
          </div>

          <!-- ==================== TAB 8: PROFILE ==================== -->
          <div class="<?= $active_tab !== 'profile' ? 'tab-hidden' : '' ?>">
              <div class="card" style="margin-top: 1.5rem; max-width:650px;">
                  <div class="card-header"><h3>User Profile Information</h3></div>
                  <div style="padding:1.5rem;">
                      <table class="data-table" style="border: none;">
                          <tbody>
                              <tr>
                                  <td style="font-weight:bold; width:200px;">Username</td>
                                  <td><?= htmlspecialchars($user_profile->username ?? 'N/A') ?></td>
                              </tr>
                              <tr>
                                  <td style="font-weight:bold;">Phone Number</td>
                                  <td><?= htmlspecialchars($user_profile->phone ?? 'Not set - Please update below') ?></td>
                              </tr>
                              <tr>
                                  <td style="font-weight:bold;">Email Address</td>
                                  <td><?= htmlspecialchars($user_profile->email ?? 'N/A') ?></td>
                              </tr>
                              <tr>
                                  <td style="font-weight:bold;">System Role</td>
                                  <td><?= strtoupper(htmlspecialchars($user_profile->role ?? 'member')) ?></td>
                              </tr>
                              <tr>
                                  <td style="font-weight:bold;">Account Status</td>
                                  <td><span class="badge badge-success"><?= strtoupper(htmlspecialchars($user_profile->status ?? 'active')) ?></span></td>
                              </tr>
                              <tr>
                                  <td style="font-weight:bold;">Registration Date</td>
                                  <td><?= htmlspecialchars($user_profile->registered_at ?? 'N/A') ?></td>
                              </tr>
                              <tr>
                                  <td style="font-weight:bold;">Last Session Login</td>
                                  <td><?= htmlspecialchars($user_profile->last_login ?? 'N/A') ?></td>
                              </tr>
                          </tbody>
                      </table>
                  </div>
              </div>

              <div class="card" style="margin-top: 1.5rem; max-width:650px;">
                  <div class="card-header"><h3>Update Contact Details</h3></div>
                  <form action="dashboard.php?tab=profile" method="POST" style="padding:1.5rem;">
                      <input type="hidden" name="action" value="update_profile">
                      <div class="form-group" style="margin-bottom: 1.25rem;">
                          <label class="form-label" for="phone">Phone / WhatsApp Number</label>
                          <input class="form-input" type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user_profile->phone ?? '') ?>" placeholder="e.g. 0799031535" required>
                      </div>
                      <button type="submit" class="btn btn-brand">Update Contact Details</button>
                  </form>
              </div>
          </div>

          <!-- ==================== TAB 9: VACANCIES ==================== -->
          <div class="<?= $active_tab !== 'vacancies' ? 'tab-hidden' : '' ?>">
              <div class="card" style="margin-top: 1.5rem; max-width: 800px; border-top: 4px solid var(--color-primary);">
                  <div class="card-header">
                      <h3>Search for Vacancies</h3>
                  </div>
                  <div style="padding: 1.5rem;">
                      <?php if(count($vacancies) > 0): ?>
                          <div style="display: grid; gap: 1rem;">
                          <?php foreach($vacancies as $v): ?>
                              <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; background: #f8fafc;">
                                  <h4 style="font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 0.5rem; text-transform: uppercase;"><?= htmlspecialchars($v->title) ?></h4>
                                  <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 1rem; font-weight: 600;"><?= htmlspecialchars($v->description) ?></p>
                                  
                                  <div id="job-card-<?= $v->id ?>">
                                      <button type="button" class="btn btn-brand" style="padding: 0.5rem 1rem; font-size: 0.875rem;" onclick="document.getElementById('job-form-<?= $v->id ?>').style.display='block'; this.style.display='none';">
                                          Register / Occupy Job
                                      </button>

                                      <form id="job-form-<?= $v->id ?>" action="dashboard.php?tab=vacancies" method="POST" style="display:none; background:#fff; padding:1rem; border-radius:6px; border:1px solid #e2e8f0; margin-top: 1rem;">
                                          <input type="hidden" name="action" value="occupy_job">
                                          <input type="hidden" name="job_id" value="<?= $v->id ?>">
                                          <div style="margin-bottom: 0.75rem;">
                                              <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.25rem;">Contact Email</label>
                                              <input type="email" name="applicant_email" value="<?= htmlspecialchars($user_profile->email ?? '') ?>" required pattern=".*@gmail\.com$" title="Please provide a valid @gmail.com address" placeholder="e.g. user@gmail.com" style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:4px;">
                                          </div>
                                          <div style="margin-bottom: 1rem;">
                                              <label style="display:block; font-size:0.875rem; font-weight:600; margin-bottom:0.25rem;">Phone Number</label>
                                              <input type="tel" name="applicant_phone" value="<?= htmlspecialchars($user_profile->phone ?? '') ?>" required pattern="^\+[0-9]{7,15}$" title="Phone number must start with a + country code followed by numbers only (e.g., +254712345678)" placeholder="e.g. +2547..." style="width:100%; padding:0.5rem; border:1px solid #cbd5e1; border-radius:4px;">
                                          </div>
                                          <button type="submit" class="btn btn-brand" style="padding: 0.5rem 1rem; font-size: 0.875rem; width:100%;" onclick="return confirm('Confirm your registration for this vacancy?')">
                                              Submit Registration
                                          </button>
                                      </form>
                                  </div>
                              </div>
                          <?php endforeach; ?>
                          </div>
                      <?php else: ?>
                          <div style="padding: 3rem 1.5rem; text-align: center;">
                              <div style="font-size: 3.5rem; margin-bottom: 1.5rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.08));">🔍</div>
                              <h2 style="font-size: 1.6rem; font-weight: 800; color: #dc2626; margin-bottom: 1rem; font-family: var(--font-heading); letter-spacing: -0.025em; text-transform: uppercase;">
                                  NO VACANCIES AT THE MOMENT PLEASE
                              </h2>
                              <p style="color: var(--color-text-muted); font-size: 0.95rem; max-width: 500px; margin: 0 auto; line-height: 1.6;">
                                  All cooperative facility chores and staffing positions are currently fully occupied. Please check back later.
                              </p>
                          </div>
                      <?php endif; ?>
                  </div>
              </div>
          </div>

        </div>
      </main>
    </div>
  </div>

  <!-- SELL SUPPLY MODAL -->
  <div id="sellModal" class="sell-modal" style="display: none;">
      <div class="sell-modal-card">
          <h3 class="font-bold" style="font-size:1.25rem; color:var(--text-dark); margin-bottom:0.5rem;">Confirm Market Supply Sale</h3>
          <p class="text-sm text-muted mb-4">Sell to: <strong id="modalBuyerName" style="color:var(--text-dark);"></strong> at <strong id="modalBuyerRate" style="color:var(--success);"></strong> / Litre</p>
          
          <form action="dashboard.php?tab=markets" method="POST">
              <input type="hidden" name="action" value="sell_supply">
              <input type="hidden" name="buyer_username" id="modalBuyerUsername">
              <input type="hidden" name="buyer_rate" id="modalBuyerRateVal">
              
              <div class="form-group mb-4">
                  <label class="form-label" for="modal_posting_select">Select Active Milk Supply Posting:</label>
                  <select class="form-select" id="modal_posting_select" name="posting_id" required>
                      <option value="">-- Choose Posting --</option>
                      <?php 
                      $active_found = false;
                      foreach ($my_postings as $p) {
                          if ($p->status === 'active') {
                              $active_found = true;
                              echo "<option value='{$p->id}'>ID #{$p->id} | {$p->milk_type} | {$p->liters}L (Ksh {$p->asking_price}/L)</option>";
                          }
                      }
                      if (!$active_found) {
                          echo "<option value='' disabled>No active supply postings available. Please post milk first.</option>";
                      }
                      ?>
                  </select>
              </div>
              
              <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                  <button type="button" class="btn btn-outline" onclick="closeSellModal()">Cancel</button>
                  <button type="submit" class="btn btn-brand" <?= !$active_found ? 'disabled' : '' ?>>Confirm Trade</button>
              </div>
          </form>
      </div>
  </div>


  <script src="../js/dashboard.js"></script>
  <script>
      function openSellModal(username, name, rate) {
          document.getElementById('modalBuyerUsername').value = username;
          document.getElementById('modalBuyerRateVal').value = rate;
          document.getElementById('modalBuyerName').textContent = name;
          document.getElementById('modalBuyerRate').textContent = 'Ksh ' + rate;
          document.getElementById('sellModal').style.display = 'flex';
      }
      function closeSellModal() {
          document.getElementById('sellModal').style.display = 'none';
      }

      // --- M-Pesa Integration JavaScript ---
      let currentPaymentType = "verification"; // "verification" or "post_milk"

      function switchPaymentTab(tabName) {
          document.querySelectorAll('.payment-tab').forEach(el => el.classList.remove('active'));
          document.querySelectorAll('.payment-panel').forEach(el => el.classList.remove('active'));
          
          document.getElementById('tab-' + tabName).classList.add('active');
          document.getElementById('panel-' + tabName).classList.add('active');
      }

      function initiateMpesaPayment(e) {
          e.preventDefault();
          const phoneInput = document.getElementById('mpesa_phone').value;
          const amount = 500;
          currentPaymentType = "verification";
          
          const submitBtn = document.getElementById('mpesaSubmitBtn');
          const originalBtnHTML = submitBtn.innerHTML;
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<span>Initiating payment...</span>';
          
          fetch('mpesa_ajax.php?action=stk_push', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ phone: phoneInput, amount: amount, type: 'mpesa' })
          })
          .then(res => res.json())
          .then(data => {
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalBtnHTML;
              
              if (data.status === 'success') {
                  showStkWaiting(phoneInput, 'Please check your phone and enter your M-Pesa PIN to complete the Ksh ' + amount + ' verification payment to REUBENTECH SOLUTIONS (Account 1115252).');
              } else {
                  alert('Initiation failed: ' + (data.message || 'Error occurred.'));
              }
          })
          .catch(err => {
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalBtnHTML;
              alert('Network error. Failed to initiate payment request.');
          });
      }

      function handlePostMilkSubmit(e) {
          e.preventDefault();
          const phoneInput = prompt("To publish this supply to the marketplace, a posting fee of Ksh 250 is required.\nPlease enter your M-Pesa Phone Number:", "<?= htmlspecialchars($user_profile->phone ?? '') ?>");
          if (!phoneInput) {
              alert("Posting fee payment cancelled. The listing will not be published.");
              return;
          }

          currentPaymentType = "post_milk";
          const amount = 250;
          
          const submitBtn = e.target.querySelector('button[type="submit"]');
          const originalBtnHTML = submitBtn.innerHTML;
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<span>Initiating payment...</span>';
          
          fetch('mpesa_ajax.php?action=stk_push', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ phone: phoneInput, amount: amount, type: 'mpesa' })
          })
          .then(res => res.json())
          .then(data => {
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalBtnHTML;
              
              if (data.status === 'success') {
                  showStkWaiting(phoneInput, 'Please check your phone and enter your M-Pesa PIN to pay the Ksh ' + amount + ' posting fee to REUBENTECH SOLUTIONS (Account 1115252).');
              } else {
                  alert('Initiation failed: ' + (data.message || 'Error occurred.'));
              }
          })
          .catch(err => {
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalBtnHTML;
              alert('Network error. Failed to initiate payment request.');
          });
      }

      function initiateCoopPayment(e) {
          e.preventDefault();
          const phoneInput = document.getElementById('coop_phone').value;
          const amount = 500;
          
          const submitBtn = document.getElementById('coopSubmitBtn');
          const originalBtnHTML = submitBtn.innerHTML;
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<span>Initiating payment...</span>';
          
          fetch('mpesa_ajax.php?action=stk_push', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ phone: phoneInput, amount: amount, type: 'coop' })
          })
          .then(res => res.json())
          .then(data => {
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalBtnHTML;
              
              if (data.status === 'success') {
                  showStkWaiting(phoneInput, 'Please check your phone and enter your M-Pesa PIN to complete the Ksh ' + amount + ' payment to REUBENTECH SOLUTIONS (Account 1115252).');
              } else {
                  alert('Initiation failed: ' + (data.message || 'Error occurred.'));
              }
          })
          .catch(err => {
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalBtnHTML;
              alert('Network error. Failed to initiate payment request.');
          });
      }

      // ── STK Waiting Banner ───────────────────────
      let stkPollInterval = null;

      function showStkWaiting(phone, message) {
          const existing = document.getElementById('stkWaitingBanner');
          if (existing) existing.remove();

          const banner = document.createElement('div');
          banner.id = 'stkWaitingBanner';
          banner.style.cssText = [
              'position:fixed', 'bottom:1.5rem', 'left:50%', 'transform:translateX(-50%)',
              'background:#065f46', 'color:#fff', 'padding:1rem 1.5rem', 'border-radius:12px',
              'box-shadow:0 8px 30px rgba(0,0,0,0.25)', 'z-index:9999',
              'max-width:90vw', 'text-align:center', 'font-family:sans-serif'
          ].join(';');
          banner.innerHTML = `
              <div style="font-size:1.4rem;margin-bottom:.4rem;">📲</div>
              <strong>STK Push Sent to ${phone}</strong>
              <p style="margin:.4rem 0 0;font-size:.9rem;opacity:.9;">${message}</p>
              <div style="margin-top:.8rem;font-size:.8rem;opacity:.7;">Waiting for payment confirmation&hellip;</div>
              <button onclick="dismissStkBanner()" style="margin-top:.8rem;background:rgba(255,255,255,.15);border:none;color:#fff;padding:.3rem .9rem;border-radius:6px;cursor:pointer;font-size:.85rem;">Dismiss</button>
          `;
          document.body.appendChild(banner);

          stkPollInterval = setTimeout(() => {
              dismissStkBanner();
              location.reload();
          }, 30000);
      }

      function dismissStkBanner() {
          const b = document.getElementById('stkWaitingBanner');
          if (b) b.remove();
          if (stkPollInterval) { clearTimeout(stkPollInterval); stkPollInterval = null; }
      }

  </script>
</body>
</html>
