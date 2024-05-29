# Savage Exports

## Description
- Plugin exports shipping addresses of active/pending-cancellation subscribers.
- **_Note: `Savage Exports` requires [WooCommerce](https://woocommerce.com). to be installed & activated._**

## Usage
- THe usage of this plugin is fairly simple.
  1. Running `wp export-addresses` from WP CLI will generate current month's csv file(`year-month.csv`) containing following information about each user:
     - User ID
     - First Name
     - Last Name
     - Membership Status
     - Membership Level
     - Address 1
     - Address 2
     - City
     - State
     - Zip
     - Phone
     - SMS Updates
     - What Best Represents You?
     - Email
     - Most Recent Order ID
     - Most Recent Order Date (Year/Month/Date)
     - Order Status
  2. The generated fies can be viewed in `Shipping Addresses` section of Admin panel. <br> From here files can be `downloaded` or `deleted` as per need. 
      ![Screenshot 2022-07-31 at 11 09 35 PM](https://user-images.githubusercontent.com/63953699/182039375-27de3168-31a4-4d97-8666-98ead04258ed.png)
