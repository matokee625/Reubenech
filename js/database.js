/**
 * Database Helper Module
 * Wraps Supabase queries for the Milk Project.
 * Requires: supabase-config.js to be loaded first.
 */

const Database = {
    // --- Profile & User Data ---
    async fetchUserProfile(userId) {
        const { data, error } = await supabase
            .from('users')
            .select('*')
            .eq('id', userId)
            .single();
        return { data, error };
    },

    async updateUserProfile(userId, updates) {
        const { data, error } = await supabase
            .from('users')
            .update(updates)
            .eq('id', userId)
            .select();
        return { data, error };
    },

    // --- Milk Postings ---
    async fetchMyPostings(userId) {
        const { data, error } = await supabase
            .from('milk_postings')
            .select('*')
            .eq('user_id', userId)
            .order('posted_at', { ascending: false });
        return { data, error };
    },

    async fetchAllPostings(filters = {}) {
        let query = supabase
            .from('milk_postings')
            .select(`
                *,
                users (username, phone)
            `)
            .order('posted_at', { ascending: false });

        if (filters.status && filters.status !== 'all') {
            query = query.eq('status', filters.status);
        }
        if (filters.type && filters.type !== 'all') {
            query = query.eq('milk_type', filters.type);
        }

        const { data, error } = await query;
        return { data, error };
    },

    async createPosting(userId, liters, milkType, askingPrice) {
        const { data, error } = await supabase
            .from('milk_postings')
            .insert({
                user_id: userId,
                liters: liters,
                milk_type: milkType,
                asking_price: askingPrice,
                status: 'active'
            });
        return { data, error };
    },

    async updatePostingStatus(postingId, status) {
        const { data, error } = await supabase
            .from('milk_postings')
            .update({ status: status })
            .eq('id', postingId);
        return { data, error };
    },

    // --- Transactions ---
    async fetchMyTransactions(userId) {
        const { data, error } = await supabase
            .from('transactions')
            .select(`
                *,
                buyer:users!transactions_buyer_id_fkey(username),
                milk_postings(milk_type)
            `)
            .eq('seller_id', userId)
            .order('transaction_date', { ascending: false });
        return { data, error };
    },
    
    async fetchAllTransactions(filters = {}) {
        let query = supabase
            .from('transactions')
            .select(`
                *,
                seller:users!transactions_seller_id_fkey(username),
                buyer:users!transactions_buyer_id_fkey(username),
                milk_postings(milk_type)
            `)
            .order('transaction_date', { ascending: false });
            
        if (filters.status && filters.status !== 'all') {
            query = query.eq('status', filters.status);
        }
        const { data, error } = await query;
        return { data, error };
    },

    async createTransaction(sellerId, buyerId, postingId, volume, price) {
        const { data, error } = await supabase
            .from('transactions')
            .insert({
                seller_id: sellerId,
                buyer_id: buyerId,
                posting_id: postingId,
                volume: volume,
                price: price,
                status: 'pending'
            });
        return { data, error };
    },
    
    async updateTransactionStatus(txId, status) {
        const { data, error } = await supabase
            .from('transactions')
            .update({ status: status })
            .eq('id', txId);
        return { data, error };
    },

    // --- Advertisements ---
    async fetchRecentAds(limit = 6) {
        const { data, error } = await supabase
            .from('advertisements')
            .select(`
                *,
                users(username)
            `)
            .order('created_at', { ascending: false })
            .limit(limit);
        return { data, error };
    },
    
    async fetchMyAds(userId) {
        const { data, error } = await supabase
            .from('advertisements')
            .select('*')
            .eq('user_id', userId)
            .order('created_at', { ascending: false });
        return { data, error };
    },

    async createAdvertisement(userId, title, description, imageUrl = null) {
        const { data, error } = await supabase
            .from('advertisements')
            .insert({
                user_id: userId,
                title: title,
                description: description,
                image_url: imageUrl
            });
        return { data, error };
    },

    // --- Jobs ---
    async fetchOpenJobs() {
        const { data, error } = await supabase
            .from('jobs')
            .select('*')
            .eq('status', 'open')
            .order('created_at', { ascending: false });
        return { data, error };
    },
    
    async applyForJob(jobId, userId, email, phone) {
        // Check if already applied
        const { data: existing } = await supabase
            .from('job_applications')
            .select('id')
            .eq('job_id', jobId)
            .eq('user_id', userId)
            .single();
            
        if (existing) {
            return { error: { message: 'You have already applied for this vacancy!' } };
        }
        
        const { data, error } = await supabase
            .from('job_applications')
            .insert({
                job_id: jobId,
                user_id: userId,
                email: email,
                phone: phone
            });
        return { data, error };
    },

    // --- Notifications ---
    async createNotification(type, title, message, link = null) {
        const { data, error } = await supabase
            .from('notifications')
            .insert({
                type, title, message, link
            });
        return { data, error };
    },
    
    // --- Helper ---
    async findUserByUsername(username) {
        const { data, error } = await supabase
            .from('users')
            .select('id, username, email')
            .eq('username', username)
            .limit(1)
            .single();
        return { data, error };
    }
};
