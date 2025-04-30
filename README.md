# ProcoreAPI Invoice Generator

## Overview
The ProcoreAPI Invoice Generator is a web application designed to facilitate the generation of AIA G702/G703 invoices using data from the Procore API. This application allows users to log in with their Procore API credentials, select a company, and generate invoices based on project budget data.

## File Structure
```
ProcoreAPI-Invoice
├── config
│   └── config.php
├── includes
│   └── session.php
├── src
│   ├── AiaGenerator.php
│   ├── FileDownloader.php
│   └── ProcoreApi.php
├── templates
│   ├── footer.php
│   ├── header.php
│   ├── company_select.php
│   ├── invoice_form.php
│   └── login_form.php
├── index.php
└── README.md
```

## Setup Instructions
1. **Clone the Repository**
   Clone the repository to your local machine using:
   ```
   git clone <repository-url>
   ```

2. **Install Dependencies**
   Ensure that you have PHP and a web server (like Apache or Nginx) installed. You may also need to install Composer for dependency management if required.

3. **Configuration**
   Open the `config/config.php` file and enter your Procore API credentials:
   ```php
   $config['client_id'] = 'YOUR_CLIENT_ID';
   $config['client_secret'] = 'YOUR_CLIENT_SECRET';
   ```

4. **Start the Server**
   Navigate to the project directory and start your local server. For example, if using PHP's built-in server:
   ```
   php -S localhost:8000
   ```

5. **Access the Application**
   Open your web browser and go to `http://localhost:8000/index.php` to access the application.

## Usage
- **Login**: Enter your Procore API credentials in the login form.
- **Select Company**: After logging in, select a company from the dropdown list.
- **Generate Invoice**: Fill in the invoice form with project-specific information and click the generate button to download the invoice as an Excel file.

## Contributing
Contributions are welcome! Please submit a pull request or open an issue for any enhancements or bug fixes.

## License
This project is licensed under the MIT License. See the LICENSE file for more details.
