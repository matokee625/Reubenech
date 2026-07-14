// js/dashboard-logic.js

let currentUser = null;
let currentProfile = null;
let myPostings = [];
let myTransactions = [];
let myAds = [];
let vacancies = [];

const markets = [
    { username: 'brookside_plant', name: 'Brookside Processing Plant', demand: '2,000 Liters Needed', location: 'Nairobi Industrial Area', rate: 45.00, milk_type: 'Cow' },
    { username: 'new_kcc_coop', name: 'New KCC Dairy Cooperative', demand: '500 Liters Needed', location: 'Eldoret Collection Hub', rate: 42.00, milk_type: 'Cow' },
    { username: 'githunguri_coop', name: 'Githunguri Dairy Farmers', demand: '1,200 Liters Needed', location: 'Kiambu Area', rate: 44.00, milk_type: 'Cow' }
];

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Auth Check
    const { data: { session } } = await window.supabaseClient.auth.getSession();
    if (!session) {
        window.location.href = '../login.html';
        return;
    }
    currentUser = session.user;
    
    // 2. Load User Profile
    const { data: profile } = await window.supabaseClient.from('users').select('*').eq('id', currentUser.id).single();
    if (profile) {
        currentProfile = profile;
        if (profile.role === 'admin') {
            window.location.href = '../admin/users.html';
            return;
        }
        updateProfileUI();
    }
    
    // 3. Tab switching logic
    setupTabs();
    
    // 4. Initial data load
    await loadDashboardData();
    
    // 5. Form Listeners
    setupForms();
});

function showAlert(msg, isError = false) {
    const container = document.getElementById('alert-container');
    const color = isError ? '#b91c1c' : '#166534';
    const bg = isError ? '#fef2f2' : '#f0fdf4';
    const border = isError ? '#fee2e2' : '#bbf7d0';
    const icon = isError ? '⚠️' : '✅';
    
    container.innerHTML = `
        <div style="background:${bg}; color:${color}; padding:1rem; border-radius:var(--radius-md); font-weight:bold; margin: 1rem 0; border: 1px solid ${border};">
            ${icon} ${msg}
        </div>
    `;
    setTimeout(() => { container.innerHTML = ''; }, 5000);
}

function updateProfileUI() {
    const username = currentProfile.username || currentUser.email.split('@')[0];
    const initial = username.charAt(0).toUpperCase();
    
    document.getElementById('header-avatar').textContent = initial;
    document.getElementById('header-username').textContent = username;
    document.getElementById('welcome-username').textContent = username;
    document.getElementById('status-avatar').textContent = initial;
    document.getElementById('status-username').textContent = username;
    
    if (!currentProfile.phone) {
        document.getElementById('phone-registration-card').classList.remove('tab-hidden');
    } else {
        document.getElementById('phone-registration-card').classList.add('tab-hidden');
    }
    
    // Payment Verification State
    if (currentProfile.has_paid === 1) {
        document.getElementById('verification-alert').style.display = 'none';
    } else {
        document.getElementById('verification-alert').style.display = 'block';
        if (currentProfile.has_paid === 2) {
            document.getElementById('verification-title').innerHTML = '⏳ Payment Verification Pending';
            document.getElementById('verification-message').innerHTML = 'Your verification payment has been submitted and is currently pending administrator verification. Please contact the administrator at <strong>0799031535</strong> to speed up approval.';
        }
    }
    
    // Profile Tab
    document.getElementById('profile-table').innerHTML = `
        <tbody>
            <tr><td style="font-weight:bold; width:200px;">Username</td><td>${username}</td></tr>
            <tr><td style="font-weight:bold;">Phone Number</td><td>${currentProfile.phone || 'Not set'}</td></tr>
            <tr><td style="font-weight:bold;">Email Address</td><td>${currentProfile.email || currentUser.email}</td></tr>
            <tr><td style="font-weight:bold;">System Role</td><td>${(currentProfile.role || 'member').toUpperCase()}</td></tr>
            <tr><td style="font-weight:bold;">Account Status</td><td><span class="badge badge-success">${(currentProfile.status || 'active').toUpperCase()}</span></td></tr>
            <tr><td style="font-weight:bold;">Registration Date</td><td>${currentProfile.registered_at ? new Date(currentProfile.registered_at).toLocaleString() : 'N/A'}</td></tr>
            <tr><td style="font-weight:bold;">Last Session Login</td><td>${currentProfile.last_login ? new Date(currentProfile.last_login).toLocaleString() : 'N/A'}</td></tr>
        </tbody>
    `;
    
    if (currentProfile.phone) {
        document.getElementById('profile_phone').value = currentProfile.phone;
    }
}

async function loadDashboardData() {
    try {
        // Load Postings
        const { data: postings } = await window.supabaseClient.from('milk_postings')
            .select('*').eq('user_id', currentUser.id).order('posted_at', { ascending: false });
        myPostings = postings || [];
        
        let activeVol = 0;
        let totalValue = 0;
        
        let myPostingsHtml = '';
        let activePostingsHtml = '';
        let modalOptionsHtml = '<option value="">-- Choose Posting --</option>';
        
        myPostings.forEach(p => {
            const isAct = p.status === 'active';
            if (isAct) {
                activeVol += parseFloat(p.liters);
                totalValue += (parseFloat(p.liters) * parseFloat(p.asking_price));
                
                activePostingsHtml += `<tr>
                    <td>#${String(p.id).padStart(5, '0')}</td>
                    <td>${p.milk_type}</td>
                    <td class="font-semibold">${p.liters} L</td>
                    <td>Ksh ${p.asking_price} / L</td>
                    <td><span class="badge badge-info">Active</span></td>
                </tr>`;
                
                modalOptionsHtml += `<option value='${p.id}'>ID #${p.id} | ${p.milk_type} | ${p.liters}L (Ksh ${p.asking_price}/L)</option>`;
            }
            
            const badgeClass = isAct ? 'badge-info' : (p.status==='sold' ? 'badge-success' : 'badge-danger');
            
            myPostingsHtml += `<tr>
                <td>#${String(p.id).padStart(5, '0')}</td>
                <td>${new Date(p.posted_at).toLocaleString()}</td>
                <td>${p.milk_type}</td>
                <td class="font-semibold">${p.liters} L</td>
                <td>Ksh ${p.asking_price}/L</td>
                <td><span class="badge ${badgeClass}">${p.status.toUpperCase()}</span></td>
                <td>
                    ${isAct ? `<button class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color:#dc2626; border-color:#fee2e2;" onclick="cancelPosting('${p.id}')">Cancel</button>` : '<span style="color:var(--text-muted); font-size:0.75rem;">None</span>'}
                </td>
            </tr>`;
        });
        
        const avgPrice = activeVol > 0 ? (totalValue / activeVol).toFixed(2) : '0.00';
        document.getElementById('stat-volume').textContent = activeVol.toLocaleString();
        document.getElementById('stat-price').textContent = avgPrice;
        
        document.getElementById('my-postings-table').innerHTML = myPostings.length ? myPostingsHtml : '<tr><td colspan="7" class="text-center">No postings found.</td></tr>';
        document.getElementById('active-postings-table').innerHTML = activePostingsHtml || '<tr><td colspan="5" class="text-center">No active postings.</td></tr>';
        document.getElementById('modal_posting_select').innerHTML = activeVol > 0 ? modalOptionsHtml : '<option value="" disabled>No active supply postings available.</option>';

        // Load Transactions
        const { data: txs } = await window.supabaseClient.from('transactions')
            .select('*, milk_postings(milk_type)')
            .eq('seller_id', currentUser.id).order('transaction_date', { ascending: false });
        
        myTransactions = txs || [];
        let earned = 0;
        let txsHtml = '';
        
        myTransactions.forEach(t => {
            if (t.status === 'completed') earned += parseFloat(t.price);
            
            let statusBadge = '<span class="badge badge-warning">⏳ Pending Approval</span>';
            if(t.status === 'completed') statusBadge = '<span class="badge badge-success">✅ Completed</span>';
            if(t.status === 'cancelled') statusBadge = '<span class="badge badge-danger">❌ Cancelled</span>';
            
            txsHtml += `<tr>
                <td>${new Date(t.transaction_date).toLocaleString()}</td>
                <td>#${String(t.id).padStart(5, '0')}</td>
                <td><strong>Corporate Buyer</strong></td>
                <td>${t.milk_postings?.milk_type || 'Unknown'} Milk</td>
                <td>${t.volume} L</td>
                <td class="font-semibold">Ksh ${parseFloat(t.price).toLocaleString()}</td>
                <td>${statusBadge}</td>
            </tr>`;
        });
        
        document.getElementById('stat-earnings').textContent = earned.toLocaleString();
        document.getElementById('my-transactions-table').innerHTML = myTransactions.length ? txsHtml : '<tr><td colspan="7" class="text-center">No transactions recorded yet.</td></tr>';

        // Load Markets List
        renderMarkets();
        
        // Load Ads
        const { data: ads } = await window.supabaseClient.from('advertisements').select('*').eq('user_id', currentUser.id);
        let adsHtml = '';
        (ads || []).forEach(ad => {
            adsHtml += `<div style="border: 1px solid var(--border); padding: 1rem; border-radius: var(--radius-md); display: flex; gap: 1rem;">
                <div style="width: 80px; height: 80px; background:#f1f5f9; border-radius: var(--radius-md); display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:1.5rem;">🖼️</div>
                <div>
                    <h4 class="font-bold">${ad.title}</h4>
                    <p class="text-sm text-muted" style="margin-top:0.25rem;">${ad.description}</p>
                    <span class="text-xs text-muted" style="display:block; margin-top:0.5rem;">Posted on: ${new Date(ad.created_at).toLocaleDateString()}</span>
                </div>
            </div>`;
        });
        document.getElementById('my-ads-list').innerHTML = (ads && ads.length) ? adsHtml : '<p class="text-center">No ads published.</p>';
        
        // Load Vacancies
        const { data: jobs } = await window.supabaseClient.from('jobs').select('*').eq('status', 'open');
        let jobsHtml = '';
        (jobs || []).forEach(v => {
            jobsHtml += `<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; background: #f8fafc; margin-bottom: 1rem;">
                <h4 style="font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 0.5rem; text-transform: uppercase;">${v.title}</h4>
                <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 1rem; font-weight: 600;">${v.description}</p>
                <button type="button" class="btn btn-brand" onclick="applyJob('${v.id}')">Apply for Job</button>
            </div>`;
        });
        document.getElementById('vacancies-list').innerHTML = (jobs && jobs.length) ? jobsHtml : '<div class="text-center py-8"><h2>No vacancies available.</h2></div>';
        
    } catch (err) {
        console.error('Error loading data:', err);
    }
}

function renderMarkets() {
    const list = document.getElementById('markets-list');
    let html = '';
    markets.forEach(m => {
        let actionBtn = '';
        if (currentProfile.has_paid === 1) {
            actionBtn = `<button class="btn btn-brand" onclick="openSellModal('${m.username}', '${m.name}', ${m.rate})" style="padding: 0.4rem 1rem; font-size: 0.8rem;">🤝 Sell Milk to Buyer</button>`;
        } else {
            actionBtn = `<button class="btn btn-outline" style="padding: 0.4rem 1rem; font-size: 0.8rem; border-color:#0284c7; color:#0284c7;" onclick="showAlert('You must verify your account payment first.', true)">🔒 Requires Verification</button>`;
        }
        
        html += `<div class="buyer-item" style="border: 1px solid var(--border); padding: 1.5rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
            <div class="buyer-info">
                <h4 class="font-bold" style="font-size:1.1rem; color:var(--text-dark);">${m.name}</h4>
                <p class="text-muted text-sm" style="margin-top:0.25rem;">Demand: ${m.demand}</p>
                <p class="text-muted text-sm" style="margin-top:0.25rem;">• Location: ${currentProfile.has_paid === 1 ? m.location : '🔒 Restricted'}</p>
            </div>
            <div class="buyer-action" style="text-align: right;">
                <div class="badge badge-success" style="font-size:0.9rem; padding:0.4rem 0.8rem; font-weight:bold;">Buying Rate: Ksh ${m.rate.toFixed(2)} / Litre</div>
                <div style="margin-top: 0.75rem;">${actionBtn}</div>
            </div>
        </div>`;
    });
    list.innerHTML = html;
}

function openSellModal(username, name, rate) {
    document.getElementById('modalBuyerUsername').value = username;
    document.getElementById('modalBuyerName').textContent = name;
    document.getElementById('modalBuyerRateVal').value = rate;
    document.getElementById('sellModal').style.display = 'flex';
}

function setupTabs() {
    const links = document.querySelectorAll('#nav-tabs a.sidebar-link[data-tab]');
    const contents = document.querySelectorAll('.tab-content');
    
    links.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const tabName = link.getAttribute('data-tab');
            switchTab(tabName);
        });
    });
    
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab') || 'dashboard';
    switchTab(initialTab);
}

function switchTab(tabName) {
    document.querySelectorAll('#nav-tabs a.sidebar-link[data-tab]').forEach(l => l.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('tab-hidden'));
    
    const targetLink = document.querySelector(`#nav-tabs a.sidebar-link[data-tab="${tabName}"]`);
    if(targetLink) targetLink.classList.add('active');
    
    const targetContent = document.getElementById(`tab-${tabName}`);
    if(targetContent) targetContent.classList.remove('tab-hidden');
    
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}

function setupForms() {
    // Post Milk
    document.getElementById('postMilkForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const liters = document.getElementById('liters').value;
        const type = document.getElementById('milk_type').value;
        const price = document.getElementById('asking_price').value;
        
        try {
            const { error } = await window.supabaseClient.from('milk_postings').insert([
                { user_id: currentUser.id, liters, milk_type: type, asking_price: price, status: 'active' }
            ]);
            if(error) throw error;
            showAlert('Supply posted successfully!');
            document.getElementById('postMilkForm').reset();
            await loadDashboardData();
            switchTab('postings');
        } catch(err) { showAlert(err.message, true); }
    });

    // Sell Milk (Modal)
    document.getElementById('sellSupplyForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const posting_id = document.getElementById('modal_posting_select').value;
        const buyer_username = document.getElementById('modalBuyerUsername').value;
        const rate = parseFloat(document.getElementById('modalBuyerRateVal').value);
        
        if(!posting_id) return showAlert('Select a posting', true);
        
        try {
            // Find buyer (simulation, usually you have actual buyer accounts, here we just lookup or use dummy)
            let { data: buyer } = await window.supabaseClient.from('users').select('id').eq('username', buyer_username).single();
            if(!buyer) {
                // create dummy if missing
                const res = await window.supabaseClient.from('users').insert({
                    username: buyer_username, email: buyer_username+'@cooperative.com', role: 'member', status:'active'
                }).select().single();
                buyer = res.data;
            }
            
            // Mark posting sold
            await window.supabaseClient.from('milk_postings').update({status:'sold'}).eq('id', posting_id);
            
            // Fetch posting liters to calculate price
            const p = myPostings.find(x => x.id == posting_id);
            const total = parseFloat(p.liters) * rate;
            
            // Add tx
            await window.supabaseClient.from('transactions').insert([
                { seller_id: currentUser.id, buyer_id: buyer.id, posting_id, volume: p.liters, price: total, status: 'pending' }
            ]);
            
            document.getElementById('sellModal').style.display = 'none';
            showAlert('Sale processed! Pending verification.');
            await loadDashboardData();
            switchTab('transactions');
            
        } catch(err) {
            showAlert(err.message, true);
        }
    });

    // Post Ad
    document.getElementById('postAdForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const title = document.getElementById('ad_title').value;
        const desc = document.getElementById('ad_desc').value;
        try {
            await window.supabaseClient.from('advertisements').insert([
                { user_id: currentUser.id, title, description: desc }
            ]);
            showAlert('Notice published successfully!');
            document.getElementById('postAdForm').reset();
            await loadDashboardData();
        } catch(err) { showAlert(err.message, true); }
    });

    // Update Phones
    document.getElementById('phone-update-form').addEventListener('submit', handlePhoneUpdate);
    document.getElementById('profile-update-form').addEventListener('submit', handlePhoneUpdate);
}

async function handlePhoneUpdate(e) {
    e.preventDefault();
    const phone = (e.target.querySelector('input[type="tel"]')).value;
    try {
        await window.supabaseClient.from('users').update({phone}).eq('id', currentUser.id);
        showAlert('Phone updated!');
        currentProfile.phone = phone;
        updateProfileUI();
    } catch(err) { showAlert(err.message, true); }
}

window.cancelPosting = async function(id) {
    if(!confirm('Cancel this listing?')) return;
    try {
        await window.supabaseClient.from('milk_postings').update({status:'cancelled'}).eq('id', id);
        showAlert('Posting cancelled.');
        await loadDashboardData();
    } catch(err) { showAlert(err.message, true); }
}

window.applyJob = async function(job_id) {
    if(!confirm('Confirm application?')) return;
    try {
        await window.supabaseClient.from('job_applications').insert([
            { job_id, user_id: currentUser.id, email: currentProfile.email || currentUser.email, phone: currentProfile.phone || '0000' }
        ]);
        showAlert('Applied successfully!');
    } catch(err) { showAlert(err.message, true); }
}

// STK Push Logic
let pinDigits = 0;
window.initiateMpesaPayment = function(e) {
    e.preventDefault();
    const phone = document.getElementById('mpesa_phone').value;
    if(!phone) return showAlert('Enter phone number', true);
    
    document.getElementById('stkSimulator').style.display = 'flex';
    pinDigits = 0;
    updatePinDisplay();
}

window.typePin = function(num) {
    if(pinDigits < 4) pinDigits++;
    updatePinDisplay();
}

function updatePinDisplay() {
    const dots = document.querySelectorAll('.stk-dot');
    dots.forEach((dot, index) => {
        if(index < pinDigits) dot.classList.add('filled');
        else dot.classList.remove('filled');
    });
}

window.submitPin = async function() {
    if(pinDigits < 4) {
        alert('Please enter 4 digit PIN');
        return;
    }
    
    document.getElementById('stkPopup').innerHTML = `
        <div style="padding: 2rem;">
            <div style="animation: stkSpin 1s linear infinite; font-size: 2rem; display: inline-block;">⏳</div>
            <div style="margin-top: 1rem; font-weight: bold; color: #006837;">Processing...</div>
        </div>
    `;
    
    setTimeout(async () => {
        try {
            await window.supabaseClient.from('users').update({has_paid: 2, payment_amount: 500}).eq('id', currentUser.id);
            currentProfile.has_paid = 2;
            updateProfileUI();
            
            document.getElementById('stkPopup').innerHTML = `
                <div style="padding: 2rem; color: #006837;">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">✅</div>
                    <div style="font-weight: bold;">Sent to Safaricom</div>
                    <div style="font-size: 0.8rem; margin-top: 0.5rem; color: #64748b;">You will receive an M-PESA message shortly.</div>
                </div>
            `;
            
            setTimeout(() => {
                document.getElementById('stkSimulator').style.display = 'none';
                showAlert('Payment submitted! Awaiting admin verification.');
            }, 2500);
        } catch (err) {
            alert('Error updating payment status');
            document.getElementById('stkSimulator').style.display = 'none';
        }
    }, 1500);
}
