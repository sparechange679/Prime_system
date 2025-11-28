<?php

/**
 * Document Types Configuration for Prime Cargo Limited
 * Documents that clients can upload for shipment clearance
 */

// Document categories and types
$DOCUMENT_TYPES = [
    'commercial_documents' => [
        'title' => 'Commercial Documents',
        'description' => 'Essential business and trade documents',
        'required' => true,
        'types' => [
            'commercial_invoice' => [
                'name' => 'Commercial Invoice',
                'description' => 'Detailed invoice showing goods, quantities, and values',
                'required' => true,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ],
            'packing_list' => [
                'name' => 'Packing List',
                'description' => 'Detailed list of all items in the shipment',
                'required' => true,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ],
            'bill_of_lading' => [
                'name' => 'Bill of Lading (B/L)',
                'description' => 'Shipping document issued by carrier',
                'required' => true,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ],
            'airway_bill' => [
                'name' => 'Airway Bill (AWB)',
                'description' => 'Air freight shipping document',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ]
        ]
    ],

    'customs_documents' => [
        'title' => 'Customs Documents',
        'description' => 'Documents required for customs clearance',
        'required' => true,
        'types' => [
            'customs_declaration' => [
                'name' => 'Customs Declaration Form',
                'description' => 'Official declaration of goods for customs',
                'required' => true,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ],
            'hs_code_documentation' => [
                'name' => 'HS Code Documentation',
                'description' => 'Harmonized System classification documents',
                'required' => true,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ],
            'certificate_of_origin' => [
                'name' => 'Certificate of Origin',
                'description' => 'Document certifying the country of origin',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ]
        ]
    ],

    'regulatory_documents' => [
        'title' => 'Regulatory Documents',
        'description' => 'Government and regulatory compliance documents',
        'required' => false,
        'types' => [
            'import_permit' => [
                'name' => 'Import Permit',
                'description' => 'Government-issued import authorization',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ],
            'phytosanitary_certificate' => [
                'name' => 'Phytosanitary Certificate',
                'description' => 'Plant health certificate for agricultural goods',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ],
            'health_certificate' => [
                'name' => 'Health Certificate',
                'description' => 'Health certificate for food/animal products',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ],
            'fumigation_certificate' => [
                'name' => 'Fumigation Certificate',
                'description' => 'Certificate for treated wooden packaging',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ]
        ]
    ],

    'financial_documents' => [
        'title' => 'Financial Documents',
        'description' => 'Payment and financial transaction documents',
        'required' => false,
        'types' => [
            'payment_receipt' => [
                'name' => 'Payment Receipt',
                'description' => 'Proof of payment for clearance fees',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ],
            'bank_transfer_confirmation' => [
                'name' => 'Bank Transfer Confirmation',
                'description' => 'Bank confirmation of payment transfer',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ],
            'insurance_certificate' => [
                'name' => 'Insurance Certificate',
                'description' => 'Cargo insurance documentation',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ]
        ]
    ],

    'additional_documents' => [
        'title' => 'Additional Documents',
        'description' => 'Other supporting documents',
        'required' => false,
        'types' => [
            'product_catalog' => [
                'name' => 'Product Catalog',
                'description' => 'Product specifications and details',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '10MB'
            ],
            'technical_drawings' => [
                'name' => 'Technical Drawings',
                'description' => 'Technical specifications and diagrams',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '10MB'
            ],
            'quality_certificate' => [
                'name' => 'Quality Certificate',
                'description' => 'Quality assurance documentation',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => '5MB'
            ],
            'other_documents' => [
                'name' => 'Other Documents',
                'description' => 'Any other relevant documentation',
                'required' => false,
                'file_types' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
                'max_size' => '10MB'
            ]
        ]
    ]
];

// Helper functions
function getRequiredDocuments()
{
    global $DOCUMENT_TYPES;
    $required = [];

    foreach ($DOCUMENT_TYPES as $category => $category_data) {
        foreach ($category_data['types'] as $doc_type => $doc_data) {
            if ($doc_data['required']) {
                $required[$doc_type] = $doc_data;
            }
        }
    }

    return $required;
}

function getAllowedFileTypes()
{
    global $DOCUMENT_TYPES;
    $types = [];

    foreach ($DOCUMENT_TYPES as $category => $category_data) {
        foreach ($category_data['types'] as $doc_type => $doc_data) {
            $types = array_merge($types, $doc_data['file_types']);
        }
    }

    return array_unique($types);
}

function getMaxFileSize()
{
    global $DOCUMENT_TYPES;
    $max_size = 0;

    foreach ($DOCUMENT_TYPES as $category => $category_data) {
        foreach ($category_data['types'] as $doc_type => $doc_data) {
            $size = (int)str_replace('MB', '', $doc_data['max_size']);
            if ($size > $max_size) {
                $max_size = $size;
            }
        }
    }

    return $max_size . 'MB';
}

// Export for use in other files
if (isset($export_document_types)) {
    return $DOCUMENT_TYPES;
}
