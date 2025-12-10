# MintMarket

**A Fair and Sustainable NFT Marketplace**

## About the Project
MintMarket is a digital marketplace platform designed to empower artists and collectors. It facilitates the minting, buying, and selling of NFTs (Non-Fungible Tokens) dealing with simulated cryptocurrency.

**Key Features:**
*   **NFT Minting**: Creators can upload art, add metadata (title, description), and mint NFTs on the platform.
*   **Marketplace**: A searchable / filterable feed of active listings where users can browse and purchase NFTs.
*   **Royalty System**: Built-in royalty logic ensures original creators receive a percentage of every resale.
*   **Shopping Cart**: Users can add multiple NFTs to a cart and checkout in a single transaction.
*   **Simulated Backend**: Includes a simulated blockchain ledger to record transactions immutably.
*   **User Profiles**: dedicated profiles showing owned assets, sales history, and balance.

## Team
- **Guanghan Li**
- **Dewa Khushzad**
- **Nikash Shanbhag**

## Technology Stack

### Front-end
- **HTML5 & CSS3**: Responsive layout and styling.
- **JavaScript (Vanilla)**: Dynamic interactions, AJAX data fetching, and cart management.

### Back-end
- **PHP**: Core application logic and API endpoints.
- **Database**:
    - **SQLite** (Default): Zero-configuration database for easy setup.
    - **MySQL** (Optional): Supported for scaled deployments.
- **Blockchain Simulation**: PHP-based Proof-of-Work implementation for transaction recording.

## Getting Started

### Prerequisites
- A local server environment like **XAMPP**, **MAMP**, or **PHP built-in server**.
- **PHP 7.4+**
- **PDO Extension** (enabled by default in most installations).

### Installation

1.  **Clone the repository**:
    ```bash
    git clone https://github.com/nikashs26/MintMarket.git
    cd MintMarket
    ```

2.  **Start the Server**:
    You can use the built-in PHP server for quick testing:
    ```bash
    php -S localhost:8000
    ```

3.  **Access the Application**:
    Open your browser and navigate to:
    `http://localhost:8000`

### Database Setup
The project uses SQLite by default (`mintmarket.sqlite`), which is pre-configured. No manual SQL import is required unless you switch to MySQL.
To reset the database, you can delete the `.sqlite` file and run `setup_sqlite.php` (if applicable) or check `config.php` settings.
