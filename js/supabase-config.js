import { createClient } from 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js/+esm';

export const supabase = createClient(
  'https://hcnnimmosapaiwvthvta.supabase.co',
  'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imhjbm5pbW1vc2FwYWl3dnRodnRhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODMwNzcxMDAsImV4cCI6MjA5ODY1MzEwMH0.BFVr1JKiQPxoOcEJryFItBJ89WxbeCmtCWBAKwv8cjg'
);
