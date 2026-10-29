<?php

return [
  'label' => ':label आयात करें',
  'modal' => [
    'heading' => ':label आयात करें',
    'form' => [
      'file' => [
        'label' => 'फ़ाइल',
        'placeholder' => 'एक CSV फ़ाइल अपलोड करें',
        'rules' => [
          'duplicate_columns' => '{0} फ़ाइल में एक से अधिक खाली कॉलम शीर्षक नहीं होने चाहिए।|{1,*} फ़ाइल में डुप्लिकेट कॉलम शीर्षक नहीं होने चाहिए: :columns।',
        ],
      ],
      'columns' => [
        'label' => 'कॉलम',
        'placeholder' => 'एक कॉलम चुनें',
      ],
    ],
    'actions' => [
      'download_example' => [
        'label' => 'उदाहरण CSV फ़ाइल डाउनलोड करें',
      ],
      'import' => [
        'label' => 'आयात करें',
      ],
    ],
  ],
  'notifications' => [
    'completed' => [
      'title' => 'आयात पूरा हुआ',
      'actions' => [
        'download_failed_rows_csv' => [
          'label' => 'विफल पंक्ति की जानकारी डाउनलोड करें|विफल पंक्तियों की जानकारी डाउनलोड करें',
        ],
      ],
    ],
    'max_rows' => [
      'title' => 'अपलोड की गई CSV फ़ाइल बहुत बड़ी है',
      'body' => 'आप एक बार में 1 से अधिक पंक्ति आयात नहीं कर सकते।|आप एक बार में :count से अधिक पंक्तियाँ आयात नहीं कर सकते।',
    ],
    'started' => [
      'title' => 'आयात शुरू हुआ',
      'body' => 'आपका आयात शुरू हो गया है और 1 पंक्ति पृष्ठभूमि में संसाधित की जाएगी।|आपका आयात शुरू हो गया है और :count पंक्तियाँ पृष्ठभूमि में संसाधित की जाएँगी।',
    ],
  ],
  'example_csv' => [
    'file_name' => ':importer-example',
  ],
  'failure_csv' => [
    'file_name' => 'import-:import_id-:csv_name-failed-rows',
    'error_header' => 'त्रुटि',
    'system_error' => 'सिस्टम त्रुटि, कृपया सहायता से संपर्क करें।',
    'column_mapping_required_for_new_record' => ':attribute कॉलम को फ़ाइल के किसी कॉलम से मैप नहीं किया गया, लेकिन नए रिकॉर्ड बनाने के लिए यह आवश्यक है।',
  ],
];
