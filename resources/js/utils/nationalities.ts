/**
 * Nationalities for person records (directors, shareholders, beneficial owners, board
 * members). South African first, then the SADC neighbours most often seen on South
 * African registers, then everyone else alphabetically. Stored as the demonym text,
 * so existing free-text values stay valid.
 */
const FIRST = ['South African', 'Zimbabwean', 'Mozambican', 'Motswana', 'Namibian', 'Lesotho (Mosotho)', 'Swazi (Liswati)', 'Malawian', 'Zambian']

const REST = [
  'Afghan', 'Albanian', 'Algerian', 'American', 'Andorran', 'Angolan', 'Antiguan and Barbudan', 'Argentine', 'Armenian', 'Australian', 'Austrian', 'Azerbaijani',
  'Bahamian', 'Bahraini', 'Bangladeshi', 'Barbadian', 'Belarusian', 'Belgian', 'Belizean', 'Beninese', 'Bhutanese', 'Bolivian', 'Bosnian and Herzegovinian', 'Brazilian',
  'British', 'Bruneian', 'Bulgarian', 'Burkinabé', 'Burundian', 'Cabo Verdean', 'Cambodian', 'Cameroonian', 'Canadian', 'Central African', 'Chadian', 'Chilean', 'Chinese',
  'Colombian', 'Comoran', 'Congolese (Congo-Brazzaville)', 'Congolese (DRC)', 'Costa Rican', 'Croatian', 'Cuban', 'Cypriot', 'Czech', 'Danish', 'Djiboutian', 'Dominican',
  'Dutch', 'Ecuadorian', 'Egyptian', 'Emirati', 'Equatorial Guinean', 'Eritrean', 'Estonian', 'Ethiopian', 'Fijian', 'Filipino', 'Finnish', 'French', 'Gabonese', 'Gambian',
  'Georgian', 'German', 'Ghanaian', 'Greek', 'Grenadian', 'Guatemalan', 'Guinea-Bissauan', 'Guinean', 'Guyanese', 'Haitian', 'Honduran', 'Hungarian', 'Icelandic', 'Indian',
  'Indonesian', 'Iranian', 'Iraqi', 'Irish', 'Israeli', 'Italian', 'Ivorian', 'Jamaican', 'Japanese', 'Jordanian', 'Kazakh', 'Kenyan', 'Kiribati', 'Kuwaiti', 'Kyrgyz',
  'Lao', 'Latvian', 'Lebanese', 'Liberian', 'Libyan', 'Liechtensteiner', 'Lithuanian', 'Luxembourgish', 'Malagasy', 'Malaysian', 'Maldivian', 'Malian', 'Maltese',
  'Marshallese', 'Mauritanian', 'Mauritian', 'Mexican', 'Micronesian', 'Moldovan', 'Monégasque', 'Mongolian', 'Montenegrin', 'Moroccan', 'Myanmar', 'Nauruan', 'Nepali',
  'New Zealander', 'Nicaraguan', 'Nigerian', 'Nigerien', 'North Korean', 'North Macedonian', 'Norwegian', 'Omani', 'Pakistani', 'Palauan', 'Palestinian', 'Panamanian',
  'Papua New Guinean', 'Paraguayan', 'Peruvian', 'Polish', 'Portuguese', 'Qatari', 'Romanian', 'Russian', 'Rwandan', 'Saint Lucian', 'Salvadoran', 'Samoan', 'San Marinese',
  'São Toméan', 'Saudi', 'Senegalese', 'Serbian', 'Seychellois', 'Sierra Leonean', 'Singaporean', 'Slovak', 'Slovenian', 'Solomon Islander', 'Somali', 'South Korean',
  'South Sudanese', 'Spanish', 'Sri Lankan', 'Sudanese', 'Surinamese', 'Swedish', 'Swiss', 'Syrian', 'Taiwanese', 'Tajik', 'Tanzanian', 'Thai', 'Timorese', 'Togolese',
  'Tongan', 'Trinidadian and Tobagonian', 'Tunisian', 'Turkish', 'Turkmen', 'Tuvaluan', 'Ugandan', 'Ukrainian', 'Uruguayan', 'Uzbek', 'Vanuatuan', 'Venezuelan',
  'Vietnamese', 'Yemeni',
]

export const NATIONALITIES: string[] = [...FIRST, ...REST.filter(n => !FIRST.includes(n))]

/** The list plus a value already on record that is not in it, so editing never blanks it. */
export const nationalityItems = (current?: string | null): string[] =>
  current && !NATIONALITIES.includes(current) ? [current, ...NATIONALITIES] : NATIONALITIES
