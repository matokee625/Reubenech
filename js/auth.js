/**
 * Auth Helper Module
 * Wraps Supabase Auth for login, register, logout, session management.
 * Requires: supabase-config.js to be loaded first.
 */

const Auth = {
    /**
     * Sign in with email/username and password.
     * Supabase Auth requires email, so if a username is provided,
     * we first look up the email from the users table.
     */
    async login(identity, password) {
        let email = identity;

        // If it doesn't look like an email, look up the email by username
        if (!identity.includes('@')) {
            const { data, error } = await supabase
                .from('users')
                .select('email')
                .eq('username', identity)
                .limit(1)
                .single();

            if (error || !data) {
                return { error: { message: 'Invalid username or password.' } };
            }
            email = data.email;
        }

        // Sign in with Supabase Auth
        const { data: authData, error: authError } = await supabase.auth.signInWithPassword({
            email: email,
            password: password
        });

        if (authError) {
            return { error: { message: authError.message || 'Login failed.' } };
        }

        // Fetch user profile to check status and role
        const { data: profile, error: profileError } = await supabase
            .from('users')
            .select('*')
            .eq('auth_id', authData.user.id)
            .limit(1)
            .single();

        if (profileError || !profile) {
            return { error: { message: 'User profile not found.' } };
        }

        // Check account status
        if (profile.status === 'suspended') {
            await supabase.auth.signOut();
            if (!profile.last_login) {
                return { error: { message: 'Your account is pending administrator approval.' } };
            }
            return { error: { message: 'Your account has been suspended.' } };
        }
        if (profile.status === 'trash') {
            await supabase.auth.signOut();
            return { error: { message: 'Your account has been deleted.' } };
        }

        // Update last login (non-blocking)
        supabase
            .from('users')
            .update({ last_login: new Date().toISOString() })
            .eq('id', profile.id)
            .then(() => {});

        // Log access (non-blocking)
        supabase.from('access_logs').insert({
            user_id: profile.id,
            username: profile.username,
            action: 'login',
            ip_address: 'client',
            user_agent: navigator.userAgent
        }).then(() => {});

        return { data: { user: authData.user, profile: profile } };
    },

    /**
     * Register a new user account.
     */
    async register(username, email, password) {
        // Check if username or email exists
        const { data: existing } = await supabase
            .from('users')
            .select('id')
            .or(`username.eq.${username},email.eq.${email}`)
            .limit(1);

        if (existing && existing.length > 0) {
            return { error: { message: 'Username or email already exists.' } };
        }

        // Create auth user
        const { data: authData, error: authError } = await supabase.auth.signUp({
            email: email,
            password: password
        });

        if (authError) {
            return { error: { message: authError.message || 'Registration failed.' } };
        }

        // Insert profile — new users start as suspended (pending admin approval)
        const { error: profileError } = await supabase.from('users').insert({
            auth_id: authData.user.id,
            username: username,
            email: email,
            role: 'member',
            status: 'suspended',
            has_paid: 0
        });

        if (profileError) {
            return { error: { message: 'Failed to create user profile: ' + profileError.message } };
        }

        // Auto-seed a starter milk posting
        const { data: newUser } = await supabase
            .from('users')
            .select('id')
            .eq('auth_id', authData.user.id)
            .single();

        if (newUser) {
            // Initial milk posting is now handled dynamically through the UI if provided.
        }

        // Create notification for admin
        await supabase.from('notifications').insert({
            type: 'warning',
            title: 'New Registration',
            message: `New user '${username}' (${email}) has registered and is awaiting approval.`,
            link: 'users.html?status=suspended'
        });

        // Sign out — they can't log in until admin approves
        await supabase.auth.signOut();

        return { data: { message: 'Registration successful! Your account is pending administrator approval.' } };
    },

    /**
     * Sign out the current user.
     */
    async logout() {
        await supabase.auth.signOut();
        window.location.href = Auth.getBasePath() + 'login.html';
    },

    /**
     * Get current authenticated user and profile.
     * Returns null if not logged in.
     */
    async getCurrentUser() {
        const { data: { session } } = await supabase.auth.getSession();
        if (!session) return null;

        const { data: profile } = await supabase
            .from('users')
            .select('*')
            .eq('auth_id', session.user.id)
            .limit(1)
            .single();

        return profile ? { user: session.user, profile: profile } : null;
    },

    /**
     * Require authentication — redirect to login if not signed in.
     */
    async requireAuth() {
        const current = await Auth.getCurrentUser();
        if (!current) {
            window.location.href = Auth.getBasePath() + 'login.html';
            return null;
        }
        return current;
    },

    /**
     * Require admin role — redirect if not admin.
     */
    async requireAdmin() {
        const current = await Auth.requireAuth();
        if (!current) return null;
        if (current.profile.role !== 'admin') {
            window.location.href = Auth.getBasePath() + 'member/dashboard.html?error=unauthorized';
            return null;
        }
        return current;
    },

    /**
     * Determine base path (handles being in subdirectory like /admin/ or /member/)
     */
    getBasePath() {
        const path = window.location.pathname;
        if (path.includes('/admin/') || path.includes('/member/')) {
            return '../';
        }
        return '';
    },

    /**
     * Listen for auth state changes.
     */
    onAuthStateChange(callback) {
        supabase.auth.onAuthStateChange((event, session) => {
            callback(event, session);
        });
    }
};
