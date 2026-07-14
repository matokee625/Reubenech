// js/admin-logic.js

let currentUser = null;

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Auth Check
    const { data: { session } } = await window.supabaseClient.auth.getSession();
    if (!session) {
        window.location.href = '../login.html';
        return;
    }
    currentUser = session.user;
    
    // 2. Admin Role Check
    const { data: profile } = await window.supabaseClient.from('users').select('*').eq('id', currentUser.id).single();
    if (!profile || profile.role !== 'admin') {
        window.location.href = '../member/dashboard.html';
        return;
    }
    
    const username = profile.username || currentUser.email.split('@')[0];
    document.getElementById('admin-username').textContent = username;
    document.getElementById('admin-avatar').textContent = username.charAt(0).toUpperCase();

    // 3. Setup tabs
    setupAdminTabs();
    
    // 4. Load Data
    await loadAdminData();
});

function showAlert(msg, isError = false) {
    const container = document.getElementById('alert-container');
    const color = isError ? '#b91c1c' : '#166534';
    const bg = isError ? '#fef2f2' : '#f0fdf4';
    container.innerHTML = `<div style="background:${bg}; color:${color}; padding:1rem; border-radius:4px; font-weight:bold; margin-bottom: 1rem;">${msg}</div>`;
    setTimeout(() => { container.innerHTML = ''; }, 4000);
}

function setupAdminTabs() {
    const links = document.querySelectorAll('#admin-nav a.nav-item');
    links.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const tabName = link.getAttribute('data-tab');
            switchTab(tabName);
        });
    });
    
    // Default to homepage
    switchTab('homepage');
}

function switchTab(tabName) {
    document.querySelectorAll('#admin-nav a.nav-item').forEach(l => l.classList.remove('active'));
    document.querySelectorAll('.admin-tab').forEach(c => c.classList.add('tab-hidden'));
    
    const targetLink = document.querySelector(`#admin-nav a.nav-item[data-tab="${tabName}"]`);
    if(targetLink) targetLink.classList.add('active');
    
    const targetContent = document.getElementById(`tab-${tabName}`);
    if(targetContent) targetContent.classList.remove('tab-hidden');
}

async function loadAdminData() {
    try {
        // Stats
        const { count: usersCount } = await window.supabaseClient.from('users').select('*', { count: 'exact', head: true });
        const { count: txCount } = await window.supabaseClient.from('transactions').select('*', { count: 'exact', head: true });
        const { count: jobsCount } = await window.supabaseClient.from('jobs').select('*', { count: 'exact', head: true });
        
        document.getElementById('dashboard-stats').innerHTML = `
            <div class="stat-card blue"><div class="stat-card-label">Total Users</div><div class="stat-card-value">${usersCount}</div></div>
            <div class="stat-card green"><div class="stat-card-label">Total Transactions</div><div class="stat-card-value">${txCount}</div></div>
            <div class="stat-card amber"><div class="stat-card-label">Jobs Posted</div><div class="stat-card-value">${jobsCount}</div></div>
        `;

        // Users
        const { data: users } = await window.supabaseClient.from('users').select('*').order('registered_at', { ascending: false });
        let usersHtml = '';
        (users || []).forEach(u => {
            let pmtBtn = u.has_paid === 2 ? `<button class="btn" style="background:#ca8a04; color:#fff; font-size:0.75rem; padding:0.25rem 0.5rem;" onclick="approvePayment('${u.id}')">Approve Payment</button>` : '';
            if(u.has_paid === 1) pmtBtn = '✅ Paid';
            
            usersHtml += `<tr>
                <td>${u.username || 'No name'}</td>
                <td>${u.phone || 'No phone'}</td>
                <td>${u.email}</td>
                <td>${u.role}</td>
                <td>${u.status}</td>
                <td>${pmtBtn}</td>
                <td>
                    <button class="btn btn-outline" style="font-size:0.75rem; padding:0.25rem;" onclick="deleteUser('${u.id}')">Delete</button>
                </td>
            </tr>`;
        });
        document.getElementById('users-table-body').innerHTML = usersHtml || '<tr><td colspan="7">No users found.</td></tr>';

        // Transactions
        const { data: txs } = await window.supabaseClient.from('transactions').select('*, users!seller_id(username)').order('transaction_date', { ascending: false });
        let txsHtml = '';
        (txs || []).forEach(t => {
            let actBtn = t.status === 'pending' ? `<button class="btn" style="background:#059669; color:#fff; font-size:0.75rem; padding:0.25rem 0.5rem;" onclick="completeTx('${t.id}')">Complete</button>` : 'None';
            txsHtml += `<tr>
                <td>${new Date(t.transaction_date).toLocaleDateString()}</td>
                <td>#${t.id}</td>
                <td>${t.users?.username || 'Unknown'}</td>
                <td>${t.volume} L</td>
                <td>Ksh ${t.price}</td>
                <td>${t.status}</td>
                <td>${actBtn}</td>
            </tr>`;
        });
        document.getElementById('transactions-table-body').innerHTML = txsHtml || '<tr><td colspan="7">No transactions.</td></tr>';

        // Jobs
        const { data: jobs } = await window.supabaseClient.from('jobs').select('*');
        let jobsHtml = '';
        (jobs || []).forEach(j => {
            jobsHtml += `<tr>
                <td>${j.title}</td>
                <td>${j.description.substring(0, 50)}...</td>
                <td>${j.status}</td>
                <td><button class="btn btn-outline" style="color:red; font-size:0.75rem; padding:0.25rem;" onclick="deleteJob('${j.id}')">Remove</button></td>
            </tr>`;
        });
        document.getElementById('jobs-table-body').innerHTML = jobsHtml || '<tr><td colspan="4">No jobs.</td></tr>';

        // Job Apps
        const { data: apps } = await window.supabaseClient.from('job_applications').select('*, users(username)');
        let appsHtml = '';
        (apps || []).forEach(a => {
            appsHtml += `<tr>
                <td>#${a.job_id}</td>
                <td>${a.users?.username || 'Unknown'}</td>
                <td>${a.email}</td>
                <td>${a.phone}</td>
                <td>${new Date(a.applied_at).toLocaleDateString()}</td>
            </tr>`;
        });
        document.getElementById('applications-table-body').innerHTML = appsHtml || '<tr><td colspan="5">No applications.</td></tr>';

        // Ads
        const { data: ads } = await window.supabaseClient.from('advertisements').select('*, users(username)');
        let adsHtml = '';
        (ads || []).forEach(a => {
            adsHtml += `<tr>
                <td>#${a.id}</td>
                <td>${a.users?.username || 'Unknown'}</td>
                <td>${a.title}</td>
                <td>${a.description.substring(0,50)}...</td>
                <td>${new Date(a.created_at).toLocaleDateString()}</td>
                <td><button class="btn btn-outline" style="color:red; font-size:0.75rem; padding:0.25rem;" onclick="deleteAd('${a.id}')">Remove</button></td>
            </tr>`;
        });
        document.getElementById('ads-table-body').innerHTML = adsHtml || '<tr><td colspan="6">No ads.</td></tr>';

        // Notifs
        const { data: notifs } = await window.supabaseClient.from('notifications').select('*').order('created_at', {ascending: false});
        let notifsHtml = '';
        (notifs || []).forEach(n => {
            notifsHtml += `<tr>
                <td>${new Date(n.created_at).toLocaleString()}</td>
                <td>${n.type}</td>
                <td>${n.title}</td>
                <td>${n.message}</td>
            </tr>`;
        });
        document.getElementById('notifs-table-body').innerHTML = notifsHtml || '<tr><td colspan="4">No notifications.</td></tr>';

    } catch(err) {
        console.error(err);
        showAlert('Error loading data: ' + err.message, true);
    }
}

// Action handlers
window.approvePayment = async (id) => {
    if(!confirm('Approve payment verification for this user?')) return;
    try {
        await window.supabaseClient.from('users').update({has_paid: 1}).eq('id', id);
        showAlert('User approved!');
        loadAdminData();
    } catch(e) { showAlert(e.message, true); }
}

window.deleteUser = async (id) => {
    if(!confirm('Delete user completely?')) return;
    try {
        await window.supabaseClient.from('users').delete().eq('id', id);
        showAlert('User deleted!');
        loadAdminData();
    } catch(e) { showAlert(e.message, true); }
}

window.completeTx = async (id) => {
    if(!confirm('Mark transaction as completed?')) return;
    try {
        await window.supabaseClient.from('transactions').update({status: 'completed'}).eq('id', id);
        showAlert('Transaction completed!');
        loadAdminData();
    } catch(e) { showAlert(e.message, true); }
}

window.addJob = async () => {
    const title = prompt("Job Title:");
    if(!title) return;
    const desc = prompt("Job Description:");
    if(!desc) return;
    try {
        await window.supabaseClient.from('jobs').insert([{title, description: desc, status: 'open'}]);
        showAlert('Job created!');
        loadAdminData();
    } catch(e) { showAlert(e.message, true); }
}

window.deleteJob = async (id) => {
    if(!confirm('Remove this job?')) return;
    try {
        await window.supabaseClient.from('jobs').delete().eq('id', id);
        showAlert('Job removed!');
        loadAdminData();
    } catch(e) { showAlert(e.message, true); }
}

window.deleteAd = async (id) => {
    if(!confirm('Remove this ad?')) return;
    try {
        await window.supabaseClient.from('advertisements').delete().eq('id', id);
        showAlert('Ad removed!');
        loadAdminData();
    } catch(e) { showAlert(e.message, true); }
}
