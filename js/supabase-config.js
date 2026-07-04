import { createClient } from 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js/+esm';

export const supabase = createClient(
  'https://hcnnimmosapaiwvthvta.supabase.co',
  'YOUR_ANON_KEY'
);
