<?php

// Set the content type header to application/json
header('Content-Type: application/json');

// --- 1. Database Configuration (CRITICAL: Replace these placeholders) ---
// In a production environment, these credentials should be loaded from secure
// environment variables or a configuration file outside the web root.
$host = "localhost";
$port = "5432";
$dbname = "section_connection";
$user = "your_db_username";
$password = "your_secure_password";

// Connection string
$conn_string = "host={$host} port={$port} dbname={$dbname} user={$user} password={$password}";

// Attempt to establish a connection to PostgreSQL
$dbconn = pg_connect($conn_string);

if (!$dbconn) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed. Check your credentials and PHP pgsql extension.'
    ]);
    exit;
}

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// --- 2. Request Handling ---

switch ($method) {
    case 'GET':
        // Fetch all users
        try {
            $query = "SELECT user_id, username, email, created_at FROM users ORDER BY created_at DESC";
            $result = pg_query($dbconn, $query);

            if ($result) {
                $data = pg_fetch_all($result);
                // pg_fetch_all returns false if no rows are found, ensure we return an empty array if so.
                $data = $data ?: []; 
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                // Log detailed error on the server side, return generic error to client
                error_log("PostgreSQL GET Query Error: " . pg_last_error($dbconn));
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve data.']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
        }
        break;

    case 'POST':
        // Add a new user
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $username = $data['username'] ?? null;
        $email = $data['email'] ?? null;

        if (!$username || !$email) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing username or email.']);
            exit;
        }

        try {
            // Use prepared statements for security (prevents SQL injection)
            $query = 'INSERT INTO users (username, email) VALUES ($1, $2) RETURNING user_id, username, email, created_at';
            $result = pg_prepare($dbconn, "insert_user", $query);
            
            if (!$result) {
                throw new Exception("Failed to prepare statement: " . pg_last_error($dbconn));
            }
            
            $result = pg_execute($dbconn, "insert_user", [$username, $email]);

            if ($result) {
                $new_user = pg_fetch_assoc($result);
                http_response_code(201);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'User added successfully.',
                    'data' => $new_user
                ]);
            } else {
                error_log("PostgreSQL POST Query Error: " . pg_last_error($dbconn));
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to add user.']);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
        }
        break;

    default:
        // Handle unsupported methods
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
        break;
}

// Close the connection
pg_close($dbconn);

?>
