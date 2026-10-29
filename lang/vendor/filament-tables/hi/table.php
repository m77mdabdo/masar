<?php

return [
  'column_manager' => [
    'heading' => 'कॉलम',
    'actions' => [
      'apply' => [
        'label' => 'कॉलम लागू करें',
      ],
      'reorder' => [
        'label' => 'कॉलम का क्रम बदलें',
      ],
      'reset' => [
        'label' => 'रीसेट करें',
      ],
    ],
  ],
  'columns' => [
    'actions' => [
      'label' => 'क्रिया|क्रियाएँ',
    ],
    'icon' => [
      'boolean' => [
        'true' => 'हाँ',
        'false' => 'नहीं',
      ],
    ],
    'select' => [
      'loading_message' => 'लोड हो रहा है...',
      'no_options_message' => 'कोई विकल्प उपलब्ध नहीं।',
      'no_search_results_message' => 'आपकी खोज से कोई विकल्प मेल नहीं खाता।',
      'placeholder' => 'एक विकल्प चुनें',
      'searching_message' => 'खोजा जा रहा है...',
      'search_prompt' => 'खोजने के लिए लिखना शुरू करें...',
    ],
    'text' => [
      'actions' => [
        'collapse_list' => ':count कम दिखाएँ',
        'expand_list' => ':count और दिखाएँ',
      ],
      'more_list_items' => 'और :count अधिक',
    ],
  ],
  'fields' => [
    'bulk_select_page' => [
      'label' => 'बल्क क्रियाओं के लिए सभी आइटम चुनें/अचयनित करें।',
    ],
    'bulk_select_record' => [
      'label' => 'बल्क क्रियाओं के लिए आइटम :key चुनें/अचयनित करें।',
    ],
    'bulk_select_group' => [
      'label' => 'बल्क क्रियाओं के लिए समूह :title चुनें/अचयनित करें।',
    ],
    'search' => [
      'indicator' => 'खोजें',
    ],
  ],
  'summary' => [
    'heading' => 'सारांश',
    'subheadings' => [
      'all' => 'सभी :label',
      'group' => ':group सारांश',
      'page' => 'यह पेज',
    ],
    'summarizers' => [
      'average' => [
        'label' => 'औसत',
      ],
      'count' => [
        'label' => 'गिनती',
      ],
      'sum' => [
        'label' => 'योग',
      ],
    ],
  ],
  'actions' => [
    'disable_reordering' => [
      'label' => 'रिकॉर्ड का क्रम बदलना समाप्त करें',
    ],
    'enable_reordering' => [
      'label' => 'रिकॉर्ड का क्रम बदलें',
    ],
    'reorder_record' => [
      'label' => 'आइटम :key का क्रम बदलें',
    ],
    'group' => [
      'label' => 'समूह',
    ],
    'column_manager' => [
      'label' => 'कॉलम प्रबंधक',
    ],
    'toggle_record_content' => [
      'label' => 'आइटम :key विस्तृत/संक्षिप्त करें',
    ],
  ],
  'empty' => [
    'description' => 'शुरू करने के लिए एक :model बनाएँ।',
  ],
  'filters' => [
    'actions' => [
      'apply' => [
        'label' => 'फ़िल्टर लागू करें',
      ],
      'remove' => [
        'label' => 'फ़िल्टर हटाएँ',
      ],
      'remove_all' => [
        'label' => 'सभी फ़िल्टर हटाएँ',
        'tooltip' => 'सभी फ़िल्टर हटाएँ',
      ],
    ],
    'heading' => 'फ़िल्टर',
    'indicator' => 'सक्रिय फ़िल्टर',
    'select' => [
      'relationship' => [
        'empty_option_label' => 'कोई नहीं',
      ],
    ],
    'trashed' => [
      'label' => 'हटाए गए रिकॉर्ड',
      'only_trashed' => 'केवल हटाए गए रिकॉर्ड',
      'with_trashed' => 'हटाए गए रिकॉर्ड सहित',
      'without_trashed' => 'हटाए गए रिकॉर्ड के बिना',
    ],
  ],
  'grouping' => [
    'fields' => [
      'group' => [
        'label' => 'इसके अनुसार समूहित करें',
      ],
      'direction' => [
        'label' => 'समूह दिशा',
        'options' => [
          'asc' => 'आरोही',
          'desc' => 'अवरोही',
        ],
      ],
    ],
  ],
  'loading' => 'लोड हो रहा है...',
  'reorder_indicator' => 'रिकॉर्ड को खींचकर क्रम में रखें।',
  'result_count' => '{0} कोई परिणाम नहीं|{1} :count परिणाम|[2,*] :count परिणाम',
  'sorting' => [
    'fields' => [
      'column' => [
        'label' => 'इसके अनुसार क्रमबद्ध करें',
      ],
      'direction' => [
        'label' => 'क्रम की दिशा',
        'options' => [
          'asc' => 'आरोही',
          'desc' => 'अवरोही',
        ],
      ],
    ],
  ],
  'default_model_label' => 'रिकॉर्ड',
];
