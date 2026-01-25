# Library Installation Guide

## Required PHP Libraries for BuilderZ

To enable PDF/Excel export functionality, you need to install two libraries. Follow these steps:

---

## Method 1: Manual Download (Recommended for XAMPP)

### 1. Install FPDF (for PDF generation)

1. Download FPDF from: http://www.fpdf.org/en/download.php
2. Extract the ZIP file
3. Copy the `fpdf` folder to: `C:\Users\patel\Desktop\builderz\vendor\fpdf\`
4. Final path should be: `C:\Users\patel\Desktop\builderz\vendor\fpdf\fpdf.php`

### 2. Install PHPSpreadsheet (for Excel export)

Since PHPSpreadsheet requires composer, we'll use a simpler alternative for XAMPP:

**Option A: Use SimpleXLSXGen (Lightweight Excel writer)**

1. Download from: https://github.com/shuchkin/simplexlsxgen
2. Extract and copy `SimpleXLSXGen.php` to: `C:\Users\patel\Desktop\builderz\vendor\simplexlsxgen\`

**OR**

**Option B: Use basic CSV export (No installation needed)**
- Already built into PHP
- Opens in Excel

---

## Method 2: Using Composer (If you have it installed)

```bash
cd C:\Users\patel\Desktop\builderz
composer require setasign/fpdf
composer require shuchkin/simplexlsxgen
```

---

## After Installation

1. Refresh your browser
2. PDF/Excel export buttons will work automatically
3. If you see errors, check file paths in config

---

## Chart.js (for Dashboard charts)

**No installation needed!** Chart.js is loaded via CDN in the dashboard.

---

## Verification

After copying the files, your vendor folder should look like:

```
builderz/vendor/
├── fpdf/
│   └── fpdf.php
└── simplexlsxgen/
    └── SimpleXLSXGen.php
```

---

## Quick Download Links

- FPDF: http://www.fpdf.org/en/download.php
- SimpleXLSXGen: https://github.com/shuchkin/simplexlsxgen/archive/refs/heads/master.zip

---

**Note:** For this demo, I'll create wrapper functions that work with or without the libraries installed, showing appropriate messages if libraries are missing.
