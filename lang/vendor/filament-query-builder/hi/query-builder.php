<?php

return [
  'label' => 'क्वेरी बिल्डर',
  'form' => [
    'operator' => [
      'label' => 'ऑपरेटर',
    ],
    'or_groups' => [
      'label' => 'समूह',
      'group' => [
        'label' => 'समूह',
      ],
      'block' => [
        'label' => 'OR शर्त',
        'or' => 'OR',
      ],
    ],
    'rules' => [
      'label' => 'नियम',
      'item' => [
        'and' => 'AND',
      ],
    ],
  ],
  'no_rules' => '(कोई नियम नहीं)',
  'max_rules_reached_tooltip' => 'आप अधिकतम :count नियमों तक पहुँच गए हैं।',
  'item_separators' => [
    'and' => 'AND',
    'or' => 'OR',
  ],
  'operators' => [
    'is_filled' => [
      'label' => [
        'direct' => 'भरा हुआ है',
        'inverse' => 'खाली है',
      ],
      'summary' => [
        'direct' => ':attribute भरा हुआ है',
        'inverse' => ':attribute खाली है',
      ],
    ],
    'boolean' => [
      'is_true' => [
        'label' => [
          'direct' => 'सत्य है',
          'inverse' => 'असत्य है',
        ],
        'summary' => [
          'direct' => ':attribute सत्य है',
          'inverse' => ':attribute असत्य है',
        ],
      ],
    ],
    'date' => [
      'is_after' => [
        'label' => [
          'direct' => 'इसके बाद है',
          'inverse' => 'इसके बाद नहीं है',
        ],
        'summary' => [
          'direct' => ':attribute :date के बाद है',
          'inverse' => ':attribute :date के बाद नहीं है',
        ],
      ],
      'is_before' => [
        'label' => [
          'direct' => 'इससे पहले है',
          'inverse' => 'इससे पहले नहीं है',
        ],
        'summary' => [
          'direct' => ':attribute :date से पहले है',
          'inverse' => ':attribute :date से पहले नहीं है',
        ],
      ],
      'is_date' => [
        'label' => [
          'direct' => 'तारीख है',
          'inverse' => 'तारीख नहीं है',
        ],
        'summary' => [
          'direct' => ':attribute :date है',
          'inverse' => ':attribute :date नहीं है',
        ],
      ],
      'is_month' => [
        'label' => [
          'direct' => 'महीना है',
          'inverse' => 'महीना नहीं है',
        ],
        'summary' => [
          'direct' => ':attribute :month है',
          'inverse' => ':attribute :month नहीं है',
        ],
      ],
      'is_year' => [
        'label' => [
          'direct' => 'वर्ष है',
          'inverse' => 'वर्ष नहीं है',
        ],
        'summary' => [
          'direct' => ':attribute :year है',
          'inverse' => ':attribute :year नहीं है',
        ],
      ],
      'unit_labels' => [
        'second' => 'सेकंड',
        'minute' => 'मिनट',
        'hour' => 'घंटे',
        'day' => 'दिन',
        'week' => 'सप्ताह',
        'month' => 'महीने',
        'quarter' => 'तिमाहियाँ',
        'year' => 'वर्ष',
      ],
      'presets' => [
        'past_decade' => 'पिछला दशक',
        'past_5_years' => 'पिछले 5 वर्ष',
        'past_2_years' => 'पिछले 2 वर्ष',
        'past_year' => 'पिछला वर्ष',
        'past_6_months' => 'पिछले 6 महीने',
        'past_quarter' => 'पिछली तिमाही',
        'past_month' => 'पिछला महीना',
        'past_2_weeks' => 'पिछले 2 सप्ताह',
        'past_week' => 'पिछला सप्ताह',
        'past_hour' => 'पिछला घंटा',
        'past_minute' => 'पिछला मिनट',
        'this_decade' => 'यह दशक',
        'this_year' => 'यह वर्ष',
        'this_quarter' => 'यह तिमाही',
        'this_month' => 'यह महीना',
        'today' => 'आज',
        'this_hour' => 'यह घंटा',
        'this_minute' => 'यह मिनट',
        'next_minute' => 'अगला मिनट',
        'next_hour' => 'अगला घंटा',
        'next_week' => 'अगला सप्ताह',
        'next_2_weeks' => 'अगले 2 सप्ताह',
        'next_month' => 'अगला महीना',
        'next_quarter' => 'अगली तिमाही',
        'next_6_months' => 'अगले 6 महीने',
        'next_year' => 'अगला वर्ष',
        'next_2_years' => 'अगले 2 वर्ष',
        'next_5_years' => 'अगले 5 वर्ष',
        'next_decade' => 'अगला दशक',
        'custom' => 'कस्टम',
      ],
      'form' => [
        'date' => [
          'label' => 'तारीख',
        ],
        'month' => [
          'label' => 'महीना',
        ],
        'year' => [
          'label' => 'वर्ष',
        ],
        'mode' => [
          'label' => 'तारीख का प्रकार',
          'options' => [
            'absolute' => 'विशिष्ट तारीख',
            'relative' => 'चलती अवधि',
          ],
        ],
        'preset' => [
          'label' => 'समयावधि',
        ],
        'relative_value' => [
          'label' => 'कितने',
        ],
        'relative_unit' => [
          'label' => 'समय इकाई',
        ],
        'tense' => [
          'label' => 'काल',
          'options' => [
            'past' => 'भूत',
            'future' => 'भविष्य',
          ],
        ],
      ],
    ],
    'number' => [
      'equals' => [
        'label' => [
          'direct' => 'बराबर है',
          'inverse' => 'बराबर नहीं है',
        ],
        'summary' => [
          'direct' => ':attribute :number के बराबर है',
          'inverse' => ':attribute :number के बराबर नहीं है',
        ],
      ],
      'is_max' => [
        'label' => [
          'direct' => 'अधिकतम है',
          'inverse' => 'इससे अधिक है',
        ],
        'summary' => [
          'direct' => ':attribute अधिकतम :number है',
          'inverse' => ':attribute :number से अधिक है',
        ],
      ],
      'is_min' => [
        'label' => [
          'direct' => 'न्यूनतम है',
          'inverse' => 'इससे कम है',
        ],
        'summary' => [
          'direct' => ':attribute न्यूनतम :number है',
          'inverse' => ':attribute :number से कम है',
        ],
      ],
      'aggregates' => [
        'average' => [
          'label' => 'औसत',
          'summary' => ':attribute का औसत',
        ],
        'max' => [
          'label' => 'अधिकतम',
          'summary' => ':attribute का अधिकतम',
        ],
        'min' => [
          'label' => 'न्यूनतम',
          'summary' => ':attribute का न्यूनतम',
        ],
        'sum' => [
          'label' => 'योग',
          'summary' => ':attribute का योग',
        ],
      ],
      'form' => [
        'aggregate' => [
          'label' => 'समुच्चय',
        ],
        'number' => [
          'label' => 'संख्या',
        ],
      ],
    ],
    'relationship' => [
      'equals' => [
        'label' => [
          'direct' => 'में है',
          'inverse' => 'में नहीं है',
        ],
        'summary' => [
          'direct' => ':count :relationship हैं',
          'inverse' => ':count :relationship नहीं हैं',
        ],
      ],
      'has_max' => [
        'label' => [
          'direct' => 'अधिकतम है',
          'inverse' => 'इससे अधिक हैं',
        ],
        'summary' => [
          'direct' => 'अधिकतम :count :relationship हैं',
          'inverse' => ':count से अधिक :relationship हैं',
        ],
      ],
      'has_min' => [
        'label' => [
          'direct' => 'न्यूनतम है',
          'inverse' => 'इससे कम हैं',
        ],
        'summary' => [
          'direct' => 'न्यूनतम :count :relationship हैं',
          'inverse' => ':count से कम :relationship हैं',
        ],
      ],
      'is_empty' => [
        'label' => [
          'direct' => 'खाली है',
          'inverse' => 'खाली नहीं है',
        ],
        'summary' => [
          'direct' => ':relationship खाली है',
          'inverse' => ':relationship खाली नहीं है',
        ],
      ],
      'is_related_to' => [
        'label' => [
          'single' => [
            'direct' => 'है',
            'inverse' => 'नहीं है',
          ],
          'multiple' => [
            'direct' => 'शामिल है',
            'inverse' => 'शामिल नहीं है',
          ],
        ],
        'summary' => [
          'single' => [
            'direct' => ':relationship :values है',
            'inverse' => ':relationship :values नहीं है',
          ],
          'multiple' => [
            'direct' => ':relationship में :values शामिल है',
            'inverse' => ':relationship में :values शामिल नहीं है',
          ],
          'values_glue' => [
            0 => ', ',
            'final' => ' या ',
          ],
        ],
        'form' => [
          'value' => [
            'label' => 'मान',
          ],
          'values' => [
            'label' => 'मान',
          ],
        ],
      ],
      'form' => [
        'count' => [
          'label' => 'गिनती',
        ],
      ],
    ],
    'select' => [
      'is' => [
        'label' => [
          'direct' => 'है',
          'inverse' => 'नहीं है',
        ],
        'summary' => [
          'direct' => ':attribute :values है',
          'inverse' => ':attribute :values नहीं है',
          'values_glue' => [
            0 => ', ',
            'final' => ' या ',
          ],
        ],
        'form' => [
          'value' => [
            'label' => 'मान',
          ],
          'values' => [
            'label' => 'मान',
          ],
        ],
      ],
    ],
    'text' => [
      'contains' => [
        'label' => [
          'direct' => 'शामिल है',
          'inverse' => 'शामिल नहीं है',
        ],
        'summary' => [
          'direct' => ':attribute में :text शामिल है',
          'inverse' => ':attribute में :text शामिल नहीं है',
        ],
      ],
      'ends_with' => [
        'label' => [
          'direct' => 'इससे समाप्त होता है',
          'inverse' => 'इससे समाप्त नहीं होता',
        ],
        'summary' => [
          'direct' => ':attribute :text से समाप्त होता है',
          'inverse' => ':attribute :text से समाप्त नहीं होता',
        ],
      ],
      'equals' => [
        'label' => [
          'direct' => 'बराबर है',
          'inverse' => 'बराबर नहीं है',
        ],
        'summary' => [
          'direct' => ':attribute :text के बराबर है',
          'inverse' => ':attribute :text के बराबर नहीं है',
        ],
      ],
      'starts_with' => [
        'label' => [
          'direct' => 'इससे शुरू होता है',
          'inverse' => 'इससे शुरू नहीं होता',
        ],
        'summary' => [
          'direct' => ':attribute :text से शुरू होता है',
          'inverse' => ':attribute :text से शुरू नहीं होता',
        ],
      ],
      'form' => [
        'text' => [
          'label' => 'टेक्स्ट',
        ],
      ],
    ],
  ],
  'actions' => [
    'add_rule' => [
      'label' => 'नियम जोड़ें',
    ],
    'add_rule_group' => [
      'label' => 'OR जोड़ें',
    ],
  ],
];
