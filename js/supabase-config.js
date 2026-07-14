/**
 * Supabase Configuration
 * Central Supabase client initialization used by all pages.
 */
const SUPABASE_URL = 'https://hcnnimmosapaiwvthvta.supabase.co';
const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhjbm5pbW1vc2FwYWl3dnRodnRhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODMwNzcxMDAsImV4cCI6MjA5ODY1MzEwMH0.BFVr1JKiQPxoOcEJryFItBJ89WxbeCmtCWBAKwv8cjg';

// Initialize the Supabase client
// Use var to prevent 'Identifier already declared' errors with the CDN bundle
var supabaseClient = window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);
// Alias for convenience — all app code references 'supabase'
var supabase = supabaseClient;
