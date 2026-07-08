-- Supabase Database Schema for Bhardwaj Gurukul Notices System

-- Create notices table for storing uploaded notices
CREATE TABLE notices (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    title TEXT NOT NULL,
    content TEXT,
    file_url TEXT,
    file_type TEXT CHECK (file_type IN ('pdf', 'text', 'image')),
    file_size BIGINT,
    is_active BOOLEAN DEFAULT true,
    priority TEXT DEFAULT 'normal' CHECK (priority IN ('low', 'normal', 'high', 'urgent')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_by TEXT DEFAULT 'admin'
);

-- Create admins table for authentication
CREATE TABLE admins (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    full_name TEXT NOT NULL,
    is_super_admin BOOLEAN DEFAULT false,
    last_login TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create session table for authentication
CREATE TABLE admin_sessions (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    admin_id UUID REFERENCES admins(id) ON DELETE CASCADE,
    session_token TEXT UNIQUE NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Enable Row Level Security (RLS)
ALTER TABLE notices ENABLE ROW LEVEL SECURITY;
ALTER TABLE admins ENABLE ROW LEVEL SECURITY;
ALTER TABLE admin_sessions ENABLE ROW LEVEL SECURITY;

-- Create RLS policies
-- Notices: Everyone can read, only authenticated admins can write
CREATE POLICY "Public can read notices" ON notices FOR SELECT USING (true);
CREATE POLICY "Admins can manage notices" ON notices FOR ALL USING (auth.role() = 'authenticated');

-- Admins: Only users can view their own profile, super admins can view all
CREATE POLICY "Users can view own profile" ON admins FOR SELECT USING (
    auth.uid() = id OR (auth.role() = 'authenticated' AND is_super_admin = true)
);

-- Admin sessions: Only users can view their own sessions
CREATE POLICY "Users can view own sessions" ON admin_sessions FOR SELECT USING (
    auth.uid() = admin_id
);

-- Create indexes for better performance
CREATE INDEX idx_notices_active ON notices(is_active, created_at DESC);
CREATE INDEX idx_notices_priority ON notices(priority, created_at DESC);
CREATE INDEX idx_admins_username ON admins(username);
CREATE INDEX idx_admins_email ON admins(email);
CREATE INDEX idx_sessions_admin_id ON admin_sessions(admin_id);
CREATE INDEX idx_sessions_expires ON admin_sessions(expires_at);

-- Create function for updating updated_at timestamp
CREATE OR REPLACE FUNCTION handle_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create triggers for updated_at
CREATE TRIGGER handle_notices_updated_at
    BEFORE UPDATE ON notices
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

CREATE TRIGGER handle_admins_updated_at
    BEFORE UPDATE ON admins
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- Create initial super admin user (password will be set via PHP)
INSERT INTO admins (username, email, full_name, password_hash, is_super_admin)
VALUES ('superadmin', 'admin@bhardwajgurukul.com', 'Super Administrator', '$2y$10$dummy_hash_for_initial_setup', true)
ON CONFLICT (username) DO NOTHING;