# Existing APIs Reference

## All Existing APIs List

Based on your current codebase, here are all the APIs that exist and their responses:

---

## 1. **All Products API**
**Endpoint:** `GET /api_products.php`

**Headers:**
- No authentication required
- `Content-Type: application/json`

**Response (200):**
```json
{
  "status": "success",
  "message": "Products retrieved successfully",
  "count": 3,
  "data": [
    {
      "id": 1,
      "name": "Summer Dress",
      "description": "Beautiful summer dress",
      "price": "2999.00",
      "is_active": 1,
      "created_at": "2024-01-15 10:30:00",
      "category": {
        "id": 1,
        "name": "Dresses",
        "parent_id": null,
        "is_active": 1
      },
      "images": [
        {
          "id": 1,
          "image_url": "http://localhost/DressHub%20APIs/images/products/summer_dress.jpg",
          "is_primary": 1
        }
      ],
      "variants": [
        {
          "id": 1,
          "size": "M",
          "color": "Blue",
          "stock": 10,
          "created_at": "2024-01-15 10:30:00"
        }
      ],
      "bulk_prices": []
    }
  ]
}
```

**Error Response (500):**
```json
{
  "status": "error",
  "message": "Error retrieving products: [error details]"
}
```

---

## 2. **Product By ID API**
**Endpoint:** `GET /api_products.php/1` (where 1 is the product ID)

**Headers:**
- No authentication required
- `Content-Type: application/json`

**Response (200):**
```json
{
  "status": "success",
  "message": "Product retrieved successfully",
  "data": {
    "id": 1,
    "name": "Summer Dress",
    "description": "Beautiful summer dress",
    "price": "2999.00",
    "is_active": 1,
    "created_at": "2024-01-15 10:30:00",
    "category": {
      "id": 1,
      "name": "Dresses",
      "parent_id": null,
      "is_active": 1
    },
    "images": [
      {
        "id": 1,
        "image_url": "http://localhost/DressHub%20APIs/images/products/summer_dress.jpg",
        "is_primary": 1
      }
    ],
    "variants": [
      {
        "id": 1,
        "size": "M",
        "color": "Blue",
        "stock": 10,
        "created_at": "2024-01-15 10:30:00"
      }
    ],
    "bulk_prices": []
  }
}
```

**Error Response (404):**
```json
{
  "status": "error",
  "message": "Product not found"
}
```

**Error Response (500):**
```json
{
  "status": "error",
  "message": "Error retrieving product: [error details]"
}
```

---

## 3. **All Categories API**
**Endpoint:** `GET /categories.php`

**Headers:**
- No authentication required (currently no JWT check on GET)
- `Content-Type: application/json`

**Response (200):**
```json
{
  "message": "Success",
  "data": [
    {
      "id": 1,
      "name": "Dresses",
      "parent_id": null,
      "is_active": 1
    },
    {
      "id": 2,
      "name": "Shirts",
      "parent_id": null,
      "is_active": 1
    },
    {
      "id": 3,
      "name": "Summer Dresses",
      "parent_id": 1,
      "is_active": 1
    }
  ]
}
```

---

## 4. **Category By ID API**
**Endpoint:** `GET /categories.php/1` (where 1 is the category ID)

**Headers:**
- No authentication required
- `Content-Type: application/json`

**Response (200):**
```json
{
  "message": "Success",
  "data": {
    "id": 1,
    "name": "Dresses",
    "parent_id": null,
    "is_active": 1
  }
}
```

**Error Response (404):**
```json
{
  "message": "Category not found"
}
```

---

## 5. **Products By Category API**
**Endpoint:** `GET /api_products.php?category_id=1` (where 1 is the category ID)

**Headers:**
- No authentication required
- `Content-Type: application/json`

**Response (200):**
```json
{
  "status": "success",
  "message": "Products retrieved successfully",
  "count": 2,
  "category_id": 1,
  "data": [
    {
      "id": 1,
      "name": "Summer Dress",
      "description": "Beautiful summer dress",
      "price": "2999.00",
      "is_active": 1,
      "created_at": "2024-01-15 10:30:00",
      "category": {
        "id": 1,
        "name": "Dresses",
        "parent_id": null,
        "is_active": 1
      },
      "images": [
        {
          "id": 1,
          "image_url": "http://localhost/DressHub%20APIs/images/products/summer_dress.jpg",
          "is_primary": 1
        }
      ],
      "variants": [
        {
          "id": 1,
          "size": "M",
          "color": "Blue",
          "stock": 10,
          "created_at": "2024-01-15 10:30:00"
        }
      ],
      "bulk_prices": []
    }
  ]
}
```

**Error Response (404 - Category not found):**
```json
{
  "status": "error",
  "message": "Category not found"
}
```

**Error Response (500):**
```json
{
  "status": "error",
  "message": "Error retrieving products: [error details]"
}
```

---

## Previous: Products By Category API (Removed)

## Summary Table

| Feature | Endpoint | Method | Auth Required | Status |
|---------|----------|--------|---------------|--------|
| Get All Products | `/api_products.php` | GET | No | ✅ Implemented |
| Get Product By ID | `/api_products.php/ID` | GET | No | ✅ Implemented |
| Get Products By Category | `/api_products.php?category_id=ID` | GET | No | ✅ Implemented |
| Create Product | `/api_products.php` | POST | No | ✅ Implemented |
| Update Product | `/api_products.php/ID` | PUT | No | ✅ Implemented |
| Delete Product | `/api_products.php/ID` | DELETE | No | ✅ Implemented |
| Get All Categories | `/categories.php` | GET | No | ✅ Implemented |
| Get Category By ID | `/categories.php/ID` | GET | No | ✅ Implemented |
| Create Category | `/categories.php` | POST | No | ✅ Implemented |
| Update Category | `/categories.php/ID` | PUT | No | ✅ Implemented |
| Delete Category | `/categories.php/ID` | DELETE | No | ✅ Implemented |

---

## Notes

1. **Product Responses Include:**
   - Product details (id, name, description, price, is_active, created_at)
   - Category information (nested object)
   - Images array
   - Variants array
   - Bulk prices array

2. **Authentication:**
   - Products and Categories endpoints don't require JWT authentication
   - Profile API requires JWT token in Authorization header

3. **Products By Category:**
   - Use query parameter: `/api_products.php?category_id=1`
   - Returns only products belonging to that category
   - Includes validation - returns 404 if category doesn't exist
   - Returns same format as get all products
