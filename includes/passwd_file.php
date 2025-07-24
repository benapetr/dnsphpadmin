<?php

/**
 * PasswdFile class for handling user database file operations
 * 
 * This class handles all operations related to the password file format
 * used for file-based authentication in dnsphpadmin.
 * 
 * Format of the file is:
 * username:password_hash:enabled:role1,role2,...
 * 
 */
class PasswdFile
{
    private $file_path;
    
    /**
     * Cache of the file contents
     * @var array
     */
    private $users = null;
    
    /**
     * Whether the file contents have been modified
     * @var bool
     */
    private $modified = false;
    
    /**
     * Constructor
     * 
     * @param string $file_path Path to the password file
     */
    public function __construct($file_path)
    {
        $this->file_path = $file_path;
    }
    
    /**
     * Load the password file contents
     * 
     * @return bool True on success, false on failure
     */
    public function Load()
    {
        if (!file_exists($this->file_path))
        {
            // Initialize an empty array if the file doesn't exist
            $this->users = [];
            return true;
        }
        
        $this->users = [];
        $lines = file($this->file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false)
        {
            return false;
        }
        
        foreach ($lines as $line)
        {
            $parts = explode(':', $line);
            if (count($parts) >= 3)
            {
                $username = $parts[0];
                $password_hash = $parts[1];
                $enabled = ($parts[2] === 'true');
                $roles = (!empty($parts[3])) ? explode(',', $parts[3]) : [];
                
                // Store both original and lowercase username
                $this->users[] = [
                    'username' => $username,
                    'username_lower' => strtolower($username),
                    'password_hash' => $password_hash,
                    'enabled' => $enabled,
                    'roles' => $roles
                ];
            }
        }
        
        return true;
    }
    
    /**
     * Save the password file contents
     * 
     * @return bool True on success, false on failure
     */
    public function Save()
    {
        if (!$this->modified)
        {
            return true; // No changes to save
        }
        
        $lines = [];
        foreach ($this->users as $user)
        {
            $roles_str = implode(',', $user['roles']);
            $enabled_str = $user['enabled'] ? 'true' : 'false';
            $lines[] = $user['username'] . ':' . $user['password_hash'] . ':' . $enabled_str . ':' . $roles_str;
        }
        
        if (file_put_contents($this->file_path, implode("\n", $lines) . "\n", LOCK_EX) === false)
        {
            return false;
        }
        
        $this->modified = false;
        return true;
    }
    
    /**
     * Get all users from the password file
     * 
     * @return array Array of users
     */
    public function GetUsers()
    {
        if ($this->users === null)
        {
            $this->Load();
        }
        
        return $this->users;
    }
    
    /**
     * Get a user by username
     * 
     * @param string $username The username to find (case-insensitive)
     * @return array|false User data or false if not found
     */
    public function GetUser($username)
    {
        if ($this->users === null)
        {
            $this->Load();
        }
        
        $username_lower = strtolower($username);
        
        foreach ($this->users as $user)
        {
            if ($user['username_lower'] === $username_lower)
            {
                return $user;
            }
        }
        
        return false;
    }
    
    /**
     * Check if a user exists
     * 
     * @param string $username The username to check (case-insensitive)
     * @return bool True if user exists, false otherwise
     */
    public function UserExists($username)
    {
        return $this->GetUser($username) !== false;
    }
    
    /**
     * Add a new user
     * 
     * @param string $username The username
     * @param string $password The plain text password
     * @param bool $enabled Whether the account is enabled
     * @param array $roles Array of roles
     * @return bool True on success, false on failure
     */
    public function AddUser($username, $password, $enabled = true, $roles = [])
    {
        if ($this->users === null)
        {
            $this->Load();
        }
        
        if (empty($username) || empty($password))
        {
            return false;
        }
        
        // Store username in lowercase for comparison but keep original for display
        $username_lower = strtolower($username);
        
        // Check if user already exists
        if ($this->UserExists($username_lower))
        {
            return false;
        }
        
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $this->users[] = [
            'username' => $username_lower, // Store as lowercase
            'username_lower' => $username_lower,
            'password_hash' => $password_hash,
            'enabled' => $enabled,
            'roles' => $roles
        ];
        
        $this->modified = true;
        return true;
    }
    
    /**
     * Delete a user
     * 
     * @param string $username The username to delete (case-insensitive)
     * @return bool True on success, false on failure
     */
    public function DeleteUser($username)
    {
        if ($this->users === null)
        {
            $this->Load();
        }
        
        $username_lower = strtolower($username);
        $found = false;
        
        foreach ($this->users as $key => $user)
        {
            if ($user['username_lower'] === $username_lower)
            {
                unset($this->users[$key]);
                $found = true;
                break;
            }
        }
        
        if ($found)
        {
            $this->users = array_values($this->users); // Re-index array
            $this->modified = true;
            return true;
        }
        
        return false;
    }
    
    /**
     * Lock a user account
     * 
     * @param string $username The username to lock (case-insensitive)
     * @return string|bool 'already_locked' if already locked, true on success, false on failure
     */
    public function LockUser($username)
    {
        if ($this->users === null)
        {
            $this->Load();
        }
        
        $username_lower = strtolower($username);
        
        foreach ($this->users as $key => $user)
        {
            if ($user['username_lower'] === $username_lower)
            {
                if (!$user['enabled'])
                {
                    return 'already_locked';
                }
                
                $this->users[$key]['enabled'] = false;
                $this->modified = true;
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Unlock a user account
     * 
     * @param string $username The username to unlock (case-insensitive)
     * @return string|bool 'already_unlocked' if already unlocked, true on success, false on failure
     */
    public function UnlockUser($username)
    {
        if ($this->users === null)
        {
            $this->Load();
        }
        
        $username_lower = strtolower($username);
        
        foreach ($this->users as $key => $user)
        {
            if ($user['username_lower'] === $username_lower)
            {
                if ($user['enabled'])
                {
                    return 'already_unlocked';
                }
                
                $this->users[$key]['enabled'] = true;
                $this->modified = true;
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get roles for a user
     * 
     * @param string $username The username (case-insensitive)
     * @return array|false Array of roles or false if user not found
     */
    public function GetUserRoles($username)
    {
        $user = $this->GetUser($username);
        
        if ($user === false)
        {
            return false;
        }
        
        return $user['roles'];
    }
    
    /**
     * Add a role to a user
     * 
     * @param string $username The username (case-insensitive)
     * @param string $role The role to add
     * @return bool True on success, false on failure
     */
    public function AddRoleToUser($username, $role)
    {
        if ($this->users === null)
        {
            $this->Load();
        }
        
        $username_lower = strtolower($username);
        
        foreach ($this->users as $key => $user)
        {
            if ($user['username_lower'] === $username_lower)
            {
                if (in_array($role, $user['roles']))
                {
                    return true; // Role already exists
                }
                
                $this->users[$key]['roles'][] = $role;
                $this->modified = true;
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Delete a role from a user
     * 
     * @param string $username The username (case-insensitive)
     * @param string $role The role to delete
     * @return bool True on success, false on failure
     */
    public function DeleteRoleFromUser($username, $role)
    {
        if ($this->users === null)
        {
            $this->Load();
        }
        
        $username_lower = strtolower($username);
        
        foreach ($this->users as $key => $user)
        {
            if ($user['username_lower'] === $username_lower)
            {
                $role_key = array_search($role, $user['roles']);
                
                if ($role_key === false)
                {
                    return false; // Role not found
                }
                
                unset($this->users[$key]['roles'][$role_key]);
                $this->users[$key]['roles'] = array_values($this->users[$key]['roles']); // Re-index array
                $this->modified = true;
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verify a user's password
     * 
     * @param string $username The username (case-insensitive)
     * @param string $password The plain text password
     * @return bool True if password is correct, false otherwise
     */
    public function VerifyPassword($username, $password)
    {
        $user = $this->GetUser($username);
        
        if ($user === false)
        {
            return false;
        }
        
        return password_verify($password, $user['password_hash']);
    }
    
    /**
     * Change a user's password
     * 
     * @param string $username The username (case-insensitive)
     * @param string $new_password The new plain text password
     * @return bool True on success, false on failure
     */
    public function ChangePassword($username, $new_password)
    {
        if ($this->users === null)
        {
            $this->Load();
        }
        
        if (empty($new_password))
        {
            return false;
        }
        
        $username_lower = strtolower($username);
        
        foreach ($this->users as $key => $user)
        {
            if ($user['username_lower'] === $username_lower)
            {
                $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $this->users[$key]['password_hash'] = $password_hash;
                $this->modified = true;
                return true;
            }
        }
        
        return false;
    }
}
