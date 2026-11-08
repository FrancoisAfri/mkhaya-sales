--
-- PostgreSQL database dump
--


-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: countries; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.countries (id, name, a2_code, a3_code, numeric_code, dialing_code, abbreviation, created_at, updated_at) FROM stdin;
1	Afghanistan	AF	AFG	4	93	\N	\N	\N
2	Albania	AL	ALB	8	355	\N	\N	\N
3	Algeria	DZ	DZA	12	213	\N	\N	\N
4	American Samoa	AS	ASM	16	1684	\N	\N	\N
5	Andorra	AD	AND	20	376	\N	\N	\N
6	Angola	AO	AGO	24	244	\N	\N	\N
7	Anguilla	AI	AIA	660	1264	\N	\N	\N
8	Antarctica	AQ	\N	\N	0	\N	\N	\N
9	Antigua and Barbuda	AG	ATG	28	1268	\N	\N	\N
10	Argentina	AR	ARG	32	54	\N	\N	\N
11	Armenia	AM	ARM	51	374	\N	\N	\N
12	Aruba	AW	ABW	533	297	\N	\N	\N
13	Australia	AU	AUS	36	61	\N	\N	\N
14	Austria	AT	AUT	40	43	\N	\N	\N
15	Azerbaijan	AZ	AZE	31	994	\N	\N	\N
16	Bahamas	BS	BHS	44	1242	\N	\N	\N
17	Bahrain	BH	BHR	48	973	\N	\N	\N
18	Bangladesh	BD	BGD	50	880	\N	\N	\N
19	Barbados	BB	BRB	52	1246	\N	\N	\N
20	Belarus	BY	BLR	112	375	\N	\N	\N
21	Belgium	BE	BEL	56	32	\N	\N	\N
22	Belize	BZ	BLZ	84	501	\N	\N	\N
23	Benin	BJ	BEN	204	229	\N	\N	\N
24	Bermuda	BM	BMU	60	1441	\N	\N	\N
25	Bhutan	BT	BTN	64	975	\N	\N	\N
26	Bolivia	BO	BOL	68	591	\N	\N	\N
27	Bosnia and Herzegovina	BA	BIH	70	387	\N	\N	\N
28	Botswana	BW	BWA	72	267	\N	\N	\N
29	Bouvet Island	BV	\N	\N	0	\N	\N	\N
30	Brazil	BR	BRA	76	55	\N	\N	\N
31	British Indian Ocean Territory	IO	\N	\N	246	\N	\N	\N
32	Brunei Darussalam	BN	BRN	96	673	\N	\N	\N
33	Bulgaria	BG	BGR	100	359	\N	\N	\N
34	Burkina Faso	BF	BFA	854	226	\N	\N	\N
35	Burundi	BI	BDI	108	257	\N	\N	\N
36	Cambodia	KH	KHM	116	855	\N	\N	\N
37	Cameroon	CM	CMR	120	237	\N	\N	\N
38	Canada	CA	CAN	124	1	\N	\N	\N
39	Cape Verde	CV	CPV	132	238	\N	\N	\N
40	Cayman Islands	KY	CYM	136	1345	\N	\N	\N
41	Central African Republic	CF	CAF	140	236	\N	\N	\N
42	Chad	TD	TCD	148	235	\N	\N	\N
43	Chile	CL	CHL	152	56	\N	\N	\N
44	China	CN	CHN	156	86	\N	\N	\N
45	Christmas Island	CX	\N	\N	61	\N	\N	\N
46	Cocos (Keeling) Islands	CC	\N	\N	672	\N	\N	\N
47	Colombia	CO	COL	170	57	\N	\N	\N
48	Comoros	KM	COM	174	269	\N	\N	\N
49	Congo	CG	COG	178	242	\N	\N	\N
50	Congo, the Democratic Republic of the	CD	COD	180	242	\N	\N	\N
51	Cook Islands	CK	COK	184	682	\N	\N	\N
52	Costa Rica	CR	CRI	188	506	\N	\N	\N
53	Cote D'Ivoire	CI	CIV	384	225	\N	\N	\N
54	Croatia	HR	HRV	191	385	\N	\N	\N
55	Cuba	CU	CUB	192	53	\N	\N	\N
56	Cyprus	CY	CYP	196	357	\N	\N	\N
57	Czech Republic	CZ	CZE	203	420	\N	\N	\N
58	Denmark	DK	DNK	208	45	\N	\N	\N
59	Djibouti	DJ	DJI	262	253	\N	\N	\N
60	Dominica	DM	DMA	212	1767	\N	\N	\N
61	Dominican Republic	DO	DOM	214	1809	\N	\N	\N
62	Ecuador	EC	ECU	218	593	\N	\N	\N
63	Egypt	EG	EGY	818	20	\N	\N	\N
64	El Salvador	SV	SLV	222	503	\N	\N	\N
65	Equatorial Guinea	GQ	GNQ	226	240	\N	\N	\N
66	Eritrea	ER	ERI	232	291	\N	\N	\N
67	Estonia	EE	EST	233	372	\N	\N	\N
68	Ethiopia	ET	ETH	231	251	\N	\N	\N
69	Falkland Islands (Malvinas)	FK	FLK	238	500	\N	\N	\N
70	Faroe Islands	FO	FRO	234	298	\N	\N	\N
71	Fiji	FJ	FJI	242	679	\N	\N	\N
72	Finland	FI	FIN	246	358	\N	\N	\N
73	France	FR	FRA	250	33	\N	\N	\N
74	French Guiana	GF	GUF	254	594	\N	\N	\N
75	French Polynesia	PF	PYF	258	689	\N	\N	\N
76	French Southern Territories	TF	\N	\N	0	\N	\N	\N
77	Gabon	GA	GAB	266	241	\N	\N	\N
78	Gambia	GM	GMB	270	220	\N	\N	\N
79	Georgia	GE	GEO	268	995	\N	\N	\N
80	Germany	DE	DEU	276	49	\N	\N	\N
81	Ghana	GH	GHA	288	233	\N	\N	\N
82	Gibraltar	GI	GIB	292	350	\N	\N	\N
83	Greece	GR	GRC	300	30	\N	\N	\N
84	Greenland	GL	GRL	304	299	\N	\N	\N
85	Grenada	GD	GRD	308	1473	\N	\N	\N
86	Guadeloupe	GP	GLP	312	590	\N	\N	\N
87	Guam	GU	GUM	316	1671	\N	\N	\N
88	Guatemala	GT	GTM	320	502	\N	\N	\N
89	Guinea	GN	GIN	324	224	\N	\N	\N
90	Guinea-Bissau	GW	GNB	624	245	\N	\N	\N
91	Guyana	GY	GUY	328	592	\N	\N	\N
92	Haiti	HT	HTI	332	509	\N	\N	\N
93	Heard Island and Mcdonald Islands	HM	\N	\N	0	\N	\N	\N
94	Holy See (Vatican City State)	VA	VAT	336	39	\N	\N	\N
95	Honduras	HN	HND	340	504	\N	\N	\N
96	Hong Kong	HK	HKG	344	852	\N	\N	\N
97	Hungary	HU	HUN	348	36	\N	\N	\N
98	Iceland	IS	ISL	352	354	\N	\N	\N
99	India	IN	IND	356	91	\N	\N	\N
100	Indonesia	ID	IDN	360	62	\N	\N	\N
101	Iran, Islamic Republic of	IR	IRN	364	98	\N	\N	\N
102	Iraq	IQ	IRQ	368	964	\N	\N	\N
103	Ireland	IE	IRL	372	353	\N	\N	\N
104	Israel	IL	ISR	376	972	\N	\N	\N
105	Italy	IT	ITA	380	39	\N	\N	\N
106	Jamaica	JM	JAM	388	1876	\N	\N	\N
107	Japan	JP	JPN	392	81	\N	\N	\N
108	Jordan	JO	JOR	400	962	\N	\N	\N
109	Kazakhstan	KZ	KAZ	398	7	\N	\N	\N
110	Kenya	KE	KEN	404	254	\N	\N	\N
111	Kiribati	KI	KIR	296	686	\N	\N	\N
112	Korea, Democratic People's Republic of	KP	PRK	408	850	\N	\N	\N
113	Korea, Republic of	KR	KOR	410	82	\N	\N	\N
114	Kuwait	KW	KWT	414	965	\N	\N	\N
115	Kyrgyzstan	KG	KGZ	417	996	\N	\N	\N
230	Vanuatu	VU	VUT	548	678	\N	\N	\N
116	Lao People's Democratic Republic	LA	LAO	418	856	\N	\N	\N
117	Latvia	LV	LVA	428	371	\N	\N	\N
118	Lebanon	LB	LBN	422	961	\N	\N	\N
119	Lesotho	LS	LSO	426	266	\N	\N	\N
120	Liberia	LR	LBR	430	231	\N	\N	\N
121	Libyan Arab Jamahiriya	LY	LBY	434	218	\N	\N	\N
122	Liechtenstein	LI	LIE	438	423	\N	\N	\N
123	Lithuania	LT	LTU	440	370	\N	\N	\N
124	Luxembourg	LU	LUX	442	352	\N	\N	\N
125	Macao	MO	MAC	446	853	\N	\N	\N
126	Macedonia, the Former Yugoslav Republic of	MK	MKD	807	389	\N	\N	\N
127	Madagascar	MG	MDG	450	261	\N	\N	\N
128	Malawi	MW	MWI	454	265	\N	\N	\N
129	Malaysia	MY	MYS	458	60	\N	\N	\N
130	Maldives	MV	MDV	462	960	\N	\N	\N
131	Mali	ML	MLI	466	223	\N	\N	\N
132	Malta	MT	MLT	470	356	\N	\N	\N
133	Marshall Islands	MH	MHL	584	692	\N	\N	\N
134	Martinique	MQ	MTQ	474	596	\N	\N	\N
135	Mauritania	MR	MRT	478	222	\N	\N	\N
136	Mauritius	MU	MUS	480	230	\N	\N	\N
137	Mayotte	YT	\N	\N	269	\N	\N	\N
138	Mexico	MX	MEX	484	52	\N	\N	\N
139	Micronesia, Federated States of	FM	FSM	583	691	\N	\N	\N
140	Moldova, Republic of	MD	MDA	498	373	\N	\N	\N
141	Monaco	MC	MCO	492	377	\N	\N	\N
142	Mongolia	MN	MNG	496	976	\N	\N	\N
143	Montserrat	MS	MSR	500	1664	\N	\N	\N
144	Morocco	MA	MAR	504	212	\N	\N	\N
145	Mozambique	MZ	MOZ	508	258	\N	\N	\N
146	Myanmar	MM	MMR	104	95	\N	\N	\N
147	Namibia	NA	NAM	516	264	\N	\N	\N
148	Nauru	NR	NRU	520	674	\N	\N	\N
149	Nepal	NP	NPL	524	977	\N	\N	\N
150	Netherlands	NL	NLD	528	31	\N	\N	\N
151	Netherlands Antilles	AN	ANT	530	599	\N	\N	\N
152	New Caledonia	NC	NCL	540	687	\N	\N	\N
153	New Zealand	NZ	NZL	554	64	\N	\N	\N
154	Nicaragua	NI	NIC	558	505	\N	\N	\N
155	Niger	NE	NER	562	227	\N	\N	\N
156	Nigeria	NG	NGA	566	234	\N	\N	\N
157	Niue	NU	NIU	570	683	\N	\N	\N
158	Norfolk Island	NF	NFK	574	672	\N	\N	\N
159	Northern Mariana Islands	MP	MNP	580	1670	\N	\N	\N
160	Norway	NO	NOR	578	47	\N	\N	\N
161	Oman	OM	OMN	512	968	\N	\N	\N
162	Pakistan	PK	PAK	586	92	\N	\N	\N
163	Palau	PW	PLW	585	680	\N	\N	\N
164	Palestinian Territory, Occupied	PS	\N	\N	970	\N	\N	\N
165	Panama	PA	PAN	591	507	\N	\N	\N
166	Papua New Guinea	PG	PNG	598	675	\N	\N	\N
167	Paraguay	PY	PRY	600	595	\N	\N	\N
168	Peru	PE	PER	604	51	\N	\N	\N
169	Philippines	PH	PHL	608	63	\N	\N	\N
170	Pitcairn	PN	PCN	612	0	\N	\N	\N
171	Poland	PL	POL	616	48	\N	\N	\N
172	Portugal	PT	PRT	620	351	\N	\N	\N
173	Puerto Rico	PR	PRI	630	1787	\N	\N	\N
174	Qatar	QA	QAT	634	974	\N	\N	\N
175	Reunion	RE	REU	638	262	\N	\N	\N
176	Romania	RO	ROM	642	40	\N	\N	\N
177	Russian Federation	RU	RUS	643	70	\N	\N	\N
178	Rwanda	RW	RWA	646	250	\N	\N	\N
179	Saint Helena	SH	SHN	654	290	\N	\N	\N
180	Saint Kitts and Nevis	KN	KNA	659	1869	\N	\N	\N
181	Saint Lucia	LC	LCA	662	1758	\N	\N	\N
182	Saint Pierre and Miquelon	PM	SPM	666	508	\N	\N	\N
183	Saint Vincent and the Grenadines	VC	VCT	670	1784	\N	\N	\N
184	Samoa	WS	WSM	882	684	\N	\N	\N
185	San Marino	SM	SMR	674	378	\N	\N	\N
186	Sao Tome and Principe	ST	STP	678	239	\N	\N	\N
187	Saudi Arabia	SA	SAU	682	966	\N	\N	\N
188	Senegal	SN	SEN	686	221	\N	\N	\N
189	Serbia and Montenegro	CS	\N	\N	381	\N	\N	\N
190	Seychelles	SC	SYC	690	248	\N	\N	\N
191	Sierra Leone	SL	SLE	694	232	\N	\N	\N
192	Singapore	SG	SGP	702	65	\N	\N	\N
193	Slovakia	SK	SVK	703	421	\N	\N	\N
194	Slovenia	SI	SVN	705	386	\N	\N	\N
195	Solomon Islands	SB	SLB	90	677	\N	\N	\N
196	Somalia	SO	SOM	706	252	\N	\N	\N
197	South Africa	ZA	ZAF	710	27	\N	\N	\N
198	South Georgia and the South Sandwich Islands	GS	\N	\N	0	\N	\N	\N
199	Spain	ES	ESP	724	34	\N	\N	\N
200	Sri Lanka	LK	LKA	144	94	\N	\N	\N
201	Sudan	SD	SDN	736	249	\N	\N	\N
202	Suriname	SR	SUR	740	597	\N	\N	\N
203	Svalbard and Jan Mayen	SJ	SJM	744	47	\N	\N	\N
204	Swaziland	SZ	SWZ	748	268	\N	\N	\N
205	Sweden	SE	SWE	752	46	\N	\N	\N
206	Switzerland	CH	CHE	756	41	\N	\N	\N
207	Syrian Arab Republic	SY	SYR	760	963	\N	\N	\N
208	Taiwan, Province of China	TW	TWN	158	886	\N	\N	\N
209	Tajikistan	TJ	TJK	762	992	\N	\N	\N
210	Tanzania, United Republic of	TZ	TZA	834	255	\N	\N	\N
211	Thailand	TH	THA	764	66	\N	\N	\N
212	Timor-Leste	TL	\N	\N	670	\N	\N	\N
213	Togo	TG	TGO	768	228	\N	\N	\N
214	Tokelau	TK	TKL	772	690	\N	\N	\N
215	Tonga	TO	TON	776	676	\N	\N	\N
216	Trinidad and Tobago	TT	TTO	780	1868	\N	\N	\N
217	Tunisia	TN	TUN	788	216	\N	\N	\N
218	Turkey	TR	TUR	792	90	\N	\N	\N
219	Turkmenistan	TM	TKM	795	7370	\N	\N	\N
220	Turks and Caicos Islands	TC	TCA	796	1649	\N	\N	\N
221	Tuvalu	TV	TUV	798	688	\N	\N	\N
222	Uganda	UG	UGA	800	256	\N	\N	\N
223	Ukraine	UA	UKR	804	380	\N	\N	\N
224	United Arab Emirates	AE	ARE	784	971	\N	\N	\N
225	United Kingdom	GB	GBR	826	44	\N	\N	\N
226	United States	US	USA	840	1	\N	\N	\N
227	United States Minor Outlying Islands	UM	\N	\N	1	\N	\N	\N
228	Uruguay	UY	URY	858	598	\N	\N	\N
229	Uzbekistan	UZ	UZB	860	998	\N	\N	\N
231	Venezuela	VE	VEN	862	58	\N	\N	\N
232	Viet Nam	VN	VNM	704	84	\N	\N	\N
233	Virgin Islands, British	VG	VGB	92	1284	\N	\N	\N
234	Virgin Islands, U.S.	VI	VIR	850	1340	\N	\N	\N
235	Wallis and Futuna	WF	WLF	876	681	\N	\N	\N
236	Western Sahara	EH	ESH	732	212	\N	\N	\N
237	Yemen	YE	YEM	887	967	\N	\N	\N
238	Zambia	ZM	ZMB	894	260	\N	\N	\N
239	Zimbabwe	ZW	ZWE	716	263	\N	\N	\N
240	Serbia	RS	SRB	688	381	\N	\N	\N
241	Asia / Pacific Region	AP	0	0	0	\N	\N	\N
242	Montenegro	ME	MNE	499	382	\N	\N	\N
243	Aland Islands	AX	ALA	248	358	\N	\N	\N
244	Bonaire, Sint Eustatius and Saba	BQ	BES	535	599	\N	\N	\N
245	Curacao	CW	CUW	531	599	\N	\N	\N
246	Guernsey	GG	GGY	831	44	\N	\N	\N
247	Isle of Man	IM	IMN	833	44	\N	\N	\N
248	Jersey	JE	JEY	832	44	\N	\N	\N
249	Kosovo	XK	---	0	381	\N	\N	\N
250	Saint Barthelemy	BL	BLM	652	590	\N	\N	\N
251	Saint Martin	MF	MAF	663	590	\N	\N	\N
252	Sint Maarten	SX	SXM	534	1	\N	\N	\N
253	South Sudan	SS	SSD	728	211	\N	\N	\N
254	South Africa	ZA	ZAF	710	27	RSA	2026-07-12 17:44:43	2026-07-12 17:44:43
\.


--
-- Name: countries_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.countries_id_seq', 254, true);


--
-- PostgreSQL database dump complete
--


--
-- PostgreSQL database dump
--


-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: provinces; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.provinces (id, country_id, name, abbreviation, created_at, updated_at) FROM stdin;
1	254	Eastern Cape	\N	2026-07-12 17:44:43	2026-07-12 17:44:43
2	254	Free State	\N	2026-07-12 17:44:43	2026-07-12 17:44:43
3	254	Gauteng	GP	2026-07-12 17:44:43	2026-07-12 17:44:43
4	254	KwaZulu-Natal	KZN	2026-07-12 17:44:43	2026-07-12 17:44:43
5	254	Limpopo	\N	2026-07-12 17:44:43	2026-07-12 17:44:43
6	254	Mpumalanga	\N	2026-07-12 17:44:43	2026-07-12 17:44:43
7	254	North West	\N	2026-07-12 17:44:43	2026-07-12 17:44:43
8	254	Northern Cape	\N	2026-07-12 17:44:43	2026-07-12 17:44:43
9	254	Western Cape	\N	2026-07-12 17:44:43	2026-07-12 17:44:43
\.


--
-- Name: provinces_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.provinces_id_seq', 9, true);


--
-- PostgreSQL database dump complete
--


--
-- PostgreSQL database dump
--


-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: cities; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cities (id, country_id, name, created_at, updated_at) FROM stdin;
1	197	Johannesburg	2026-07-12 17:44:43	2026-07-12 17:44:43
2	197	Cape Town	2026-07-12 17:44:43	2026-07-12 17:44:43
3	197	Durban	2026-07-12 17:44:43	2026-07-12 17:44:43
4	197	Pretoria	2026-07-12 17:44:43	2026-07-12 17:44:43
5	197	Gqeberha (Port Elizabeth)	2026-07-12 17:44:43	2026-07-12 17:44:43
6	197	Bloemfontein	2026-07-12 17:44:43	2026-07-12 17:44:43
7	197	East London	2026-07-12 17:44:43	2026-07-12 17:44:43
8	197	Pietermaritzburg	2026-07-12 17:44:43	2026-07-12 17:44:43
9	197	Polokwane	2026-07-12 17:44:43	2026-07-12 17:44:43
10	197	Mbombela (Nelspruit)	2026-07-12 17:44:43	2026-07-12 17:44:43
11	197	Kimberley	2026-07-12 17:44:43	2026-07-12 17:44:43
12	197	Rustenburg	2026-07-12 17:44:43	2026-07-12 17:44:43
13	197	George	2026-07-12 17:44:43	2026-07-12 17:44:43
14	197	Soweto	2026-07-12 17:44:43	2026-07-12 17:44:43
15	197	Centurion	2026-07-12 17:44:43	2026-07-12 17:44:43
16	197	Sandton	2026-07-12 17:44:43	2026-07-12 17:44:43
17	197	Midrand	2026-07-12 17:44:43	2026-07-12 17:44:43
18	197	Roodepoort	2026-07-12 17:44:43	2026-07-12 17:44:43
19	197	Benoni	2026-07-12 17:44:43	2026-07-12 17:44:43
20	197	Boksburg	2026-07-12 17:44:43	2026-07-12 17:44:43
21	197	Vereeniging	2026-07-12 17:44:43	2026-07-12 17:44:43
22	197	Vanderbijlpark	2026-07-12 17:44:43	2026-07-12 17:44:43
23	197	Welkom	2026-07-12 17:44:43	2026-07-12 17:44:43
24	197	eMalahleni (Witbank)	2026-07-12 17:44:43	2026-07-12 17:44:43
25	197	Newcastle	2026-07-12 17:44:43	2026-07-12 17:44:43
26	197	Mthatha	2026-07-12 17:44:43	2026-07-12 17:44:43
27	197	Stellenbosch	2026-07-12 17:44:43	2026-07-12 17:44:43
28	197	Paarl	2026-07-12 17:44:43	2026-07-12 17:44:43
29	197	Upington	2026-07-12 17:44:43	2026-07-12 17:44:43
30	197	Richards Bay	2026-07-12 17:44:43	2026-07-12 17:44:43
31	197	Klerksdorp	2026-07-12 17:44:43	2026-07-12 17:44:43
32	197	Potchefstroom	2026-07-12 17:44:43	2026-07-12 17:44:43
33	197	Krugersdorp	2026-07-12 17:44:43	2026-07-12 17:44:43
34	197	Springs	2026-07-12 17:44:43	2026-07-12 17:44:43
35	197	Tembisa	2026-07-12 17:44:43	2026-07-12 17:44:43
36	197	Mamelodi	2026-07-12 17:44:43	2026-07-12 17:44:43
37	197	Randburg	2026-07-12 17:44:43	2026-07-12 17:44:43
38	197	Kempton Park	2026-07-12 17:44:43	2026-07-12 17:44:43
39	197	Germiston	2026-07-12 17:44:43	2026-07-12 17:44:43
40	197	Tzaneen	2026-07-12 17:44:43	2026-07-12 17:44:43
41	197	Thohoyandou	2026-07-12 17:44:43	2026-07-12 17:44:43
42	239	Harare	2026-07-12 17:44:43	2026-07-12 17:44:43
43	239	Bulawayo	2026-07-12 17:44:43	2026-07-12 17:44:43
44	239	Chitungwiza	2026-07-12 17:44:43	2026-07-12 17:44:43
45	239	Mutare	2026-07-12 17:44:43	2026-07-12 17:44:43
46	239	Gweru	2026-07-12 17:44:43	2026-07-12 17:44:43
47	28	Gaborone	2026-07-12 17:44:43	2026-07-12 17:44:43
48	28	Francistown	2026-07-12 17:44:43	2026-07-12 17:44:43
49	28	Molepolole	2026-07-12 17:44:43	2026-07-12 17:44:43
50	28	Maun	2026-07-12 17:44:43	2026-07-12 17:44:43
51	147	Windhoek	2026-07-12 17:44:43	2026-07-12 17:44:43
52	147	Walvis Bay	2026-07-12 17:44:43	2026-07-12 17:44:43
53	147	Swakopmund	2026-07-12 17:44:43	2026-07-12 17:44:43
54	147	Oshakati	2026-07-12 17:44:43	2026-07-12 17:44:43
55	145	Maputo	2026-07-12 17:44:43	2026-07-12 17:44:43
56	145	Matola	2026-07-12 17:44:43	2026-07-12 17:44:43
57	145	Beira	2026-07-12 17:44:43	2026-07-12 17:44:43
58	145	Nampula	2026-07-12 17:44:43	2026-07-12 17:44:43
59	238	Lusaka	2026-07-12 17:44:43	2026-07-12 17:44:43
60	238	Kitwe	2026-07-12 17:44:43	2026-07-12 17:44:43
61	238	Ndola	2026-07-12 17:44:43	2026-07-12 17:44:43
62	238	Kabwe	2026-07-12 17:44:43	2026-07-12 17:44:43
63	119	Maseru	2026-07-12 17:44:43	2026-07-12 17:44:43
64	110	Nairobi	2026-07-12 17:44:43	2026-07-12 17:44:43
65	110	Mombasa	2026-07-12 17:44:43	2026-07-12 17:44:43
66	110	Kisumu	2026-07-12 17:44:43	2026-07-12 17:44:43
67	110	Nakuru	2026-07-12 17:44:43	2026-07-12 17:44:43
68	156	Lagos	2026-07-12 17:44:43	2026-07-12 17:44:43
69	156	Abuja	2026-07-12 17:44:43	2026-07-12 17:44:43
70	156	Kano	2026-07-12 17:44:43	2026-07-12 17:44:43
71	156	Ibadan	2026-07-12 17:44:43	2026-07-12 17:44:43
72	156	Port Harcourt	2026-07-12 17:44:43	2026-07-12 17:44:43
73	81	Accra	2026-07-12 17:44:43	2026-07-12 17:44:43
74	81	Kumasi	2026-07-12 17:44:43	2026-07-12 17:44:43
75	81	Tamale	2026-07-12 17:44:43	2026-07-12 17:44:43
76	63	Cairo	2026-07-12 17:44:43	2026-07-12 17:44:43
77	63	Alexandria	2026-07-12 17:44:43	2026-07-12 17:44:43
78	63	Giza	2026-07-12 17:44:43	2026-07-12 17:44:43
79	225	London	2026-07-12 17:44:43	2026-07-12 17:44:43
80	225	Manchester	2026-07-12 17:44:43	2026-07-12 17:44:43
81	225	Birmingham	2026-07-12 17:44:43	2026-07-12 17:44:43
82	225	Leeds	2026-07-12 17:44:43	2026-07-12 17:44:43
83	225	Glasgow	2026-07-12 17:44:43	2026-07-12 17:44:43
84	225	Edinburgh	2026-07-12 17:44:43	2026-07-12 17:44:43
85	226	New York	2026-07-12 17:44:43	2026-07-12 17:44:43
86	226	Los Angeles	2026-07-12 17:44:43	2026-07-12 17:44:43
87	226	Chicago	2026-07-12 17:44:43	2026-07-12 17:44:43
88	226	Houston	2026-07-12 17:44:43	2026-07-12 17:44:43
89	226	Phoenix	2026-07-12 17:44:43	2026-07-12 17:44:43
90	226	San Francisco	2026-07-12 17:44:43	2026-07-12 17:44:43
91	226	Atlanta	2026-07-12 17:44:43	2026-07-12 17:44:43
92	226	Boston	2026-07-12 17:44:43	2026-07-12 17:44:43
93	226	Seattle	2026-07-12 17:44:43	2026-07-12 17:44:43
94	226	Miami	2026-07-12 17:44:43	2026-07-12 17:44:43
95	38	Toronto	2026-07-12 17:44:43	2026-07-12 17:44:43
96	38	Vancouver	2026-07-12 17:44:43	2026-07-12 17:44:43
97	38	Montreal	2026-07-12 17:44:43	2026-07-12 17:44:43
98	38	Calgary	2026-07-12 17:44:43	2026-07-12 17:44:43
99	38	Ottawa	2026-07-12 17:44:43	2026-07-12 17:44:43
100	13	Sydney	2026-07-12 17:44:43	2026-07-12 17:44:43
101	13	Melbourne	2026-07-12 17:44:43	2026-07-12 17:44:43
102	13	Brisbane	2026-07-12 17:44:43	2026-07-12 17:44:43
103	13	Perth	2026-07-12 17:44:43	2026-07-12 17:44:43
104	13	Adelaide	2026-07-12 17:44:43	2026-07-12 17:44:43
105	99	Mumbai	2026-07-12 17:44:43	2026-07-12 17:44:43
106	99	Delhi	2026-07-12 17:44:43	2026-07-12 17:44:43
107	99	Bengaluru	2026-07-12 17:44:43	2026-07-12 17:44:43
108	99	Hyderabad	2026-07-12 17:44:43	2026-07-12 17:44:43
109	99	Chennai	2026-07-12 17:44:43	2026-07-12 17:44:43
110	99	Pune	2026-07-12 17:44:43	2026-07-12 17:44:43
111	99	Kolkata	2026-07-12 17:44:43	2026-07-12 17:44:43
112	224	Dubai	2026-07-12 17:44:43	2026-07-12 17:44:43
113	224	Abu Dhabi	2026-07-12 17:44:43	2026-07-12 17:44:43
114	224	Sharjah	2026-07-12 17:44:43	2026-07-12 17:44:43
115	80	Berlin	2026-07-12 17:44:43	2026-07-12 17:44:43
116	80	Munich	2026-07-12 17:44:43	2026-07-12 17:44:43
117	80	Frankfurt	2026-07-12 17:44:43	2026-07-12 17:44:43
118	80	Hamburg	2026-07-12 17:44:43	2026-07-12 17:44:43
119	80	Cologne	2026-07-12 17:44:43	2026-07-12 17:44:43
120	73	Paris	2026-07-12 17:44:43	2026-07-12 17:44:43
121	73	Marseille	2026-07-12 17:44:43	2026-07-12 17:44:43
122	73	Lyon	2026-07-12 17:44:43	2026-07-12 17:44:43
123	73	Toulouse	2026-07-12 17:44:43	2026-07-12 17:44:43
124	150	Amsterdam	2026-07-12 17:44:43	2026-07-12 17:44:43
125	150	Rotterdam	2026-07-12 17:44:43	2026-07-12 17:44:43
126	150	The Hague	2026-07-12 17:44:43	2026-07-12 17:44:43
127	150	Utrecht	2026-07-12 17:44:43	2026-07-12 17:44:43
128	44	Beijing	2026-07-12 17:44:43	2026-07-12 17:44:43
129	44	Shanghai	2026-07-12 17:44:43	2026-07-12 17:44:43
130	44	Shenzhen	2026-07-12 17:44:43	2026-07-12 17:44:43
131	44	Guangzhou	2026-07-12 17:44:43	2026-07-12 17:44:43
132	30	São Paulo	2026-07-12 17:44:43	2026-07-12 17:44:43
133	30	Rio de Janeiro	2026-07-12 17:44:43	2026-07-12 17:44:43
134	30	Brasília	2026-07-12 17:44:43	2026-07-12 17:44:43
\.


--
-- Name: cities_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cities_id_seq', 134, true);


--
-- PostgreSQL database dump complete
--


--
-- PostgreSQL database dump
--


-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (id, name, guard_name, created_at, updated_at) FROM stdin;
1	fleet_manager	web	2026-07-12 17:44:38	2026-07-12 17:44:38
2	fleet_driver	web	2026-07-12 17:44:38	2026-07-12 17:44:38
3	fleet_viewer	web	2026-07-12 17:44:38	2026-07-12 17:44:38
4	user	web	2026-07-12 17:44:38	2026-07-12 17:44:38
5	team leader	web	2026-07-12 17:44:38	2026-07-12 17:44:38
6	supervisor	web	2026-07-12 17:44:38	2026-07-12 17:44:38
7	manager	web	2026-07-12 17:44:38	2026-07-12 17:44:38
8	super admin	web	2026-07-12 17:44:38	2026-07-12 17:44:38
9	HR	web	2026-07-12 17:44:38	2026-07-12 17:44:38
10	recruiter	web	2026-07-12 17:44:38	2026-07-12 17:44:38
11	compliance officer	web	2026-07-12 17:44:38	2026-07-12 17:44:38
12	senior	web	2026-07-12 17:44:38	2026-07-12 17:44:38
13	senior manager	web	2026-07-12 17:44:38	2026-07-12 17:44:38
14	executive	web	2026-07-12 17:44:38	2026-07-12 17:44:38
15	administrator	web	2026-07-12 17:44:38	2026-07-12 17:44:38
16	finance manager	web	2026-07-12 17:44:39	2026-07-12 17:44:39
17	finance officer	web	2026-07-12 17:44:39	2026-07-12 17:44:39
18	finance clerk	web	2026-07-12 17:44:39	2026-07-12 17:44:39
19	payroll officer	web	2026-07-12 17:44:39	2026-07-12 17:44:39
20	budget manager	web	2026-07-12 17:44:39	2026-07-12 17:44:39
21	lms_pa_admin	web	2026-07-12 17:44:40	2026-07-12 17:44:40
22	board_administrator	web	2026-07-12 17:44:41	2026-07-12 17:44:41
23	company_secretary	web	2026-07-12 17:44:41	2026-07-12 17:44:41
24	chairperson	web	2026-07-12 17:44:41	2026-07-12 17:44:41
25	director	web	2026-07-12 17:44:41	2026-07-12 17:44:41
26	committee_member	web	2026-07-12 17:44:41	2026-07-12 17:44:41
27	internal_auditor	web	2026-07-12 17:44:41	2026-07-12 17:44:41
28	board_observer	web	2026-07-12 17:44:41	2026-07-12 17:44:41
29	sales consultant	web	2026-07-12 17:44:41	2026-07-12 17:44:41
30	sales manager	web	2026-07-12 17:44:41	2026-07-12 17:44:41
31	sales executive	web	2026-07-12 17:44:41	2026-07-12 17:44:41
34	reseller	web	2026-07-28 11:33:45	2026-07-28 11:33:45
35	pm browser check	web	2026-08-08 15:00:55	2026-08-08 15:00:55
36	client	web	2026-09-22 15:55:43	2026-09-22 15:55:43
37	subcontractor	web	2026-09-22 15:55:43	2026-09-22 15:55:43
38	WMS · Warehouse manager	web	2026-10-13 15:48:16	2026-10-13 15:48:16
39	WMS · Receiving clerk	web	2026-10-13 15:48:16	2026-10-13 15:48:16
40	WMS · Inventory controller	web	2026-10-13 15:48:16	2026-10-13 15:48:16
41	WMS · Picker and packer	web	2026-10-13 15:48:16	2026-10-13 15:48:16
42	WMS · Dispatch clerk	web	2026-10-13 15:48:16	2026-10-13 15:48:16
43	WMS · Procurement officer	web	2026-10-13 15:48:16	2026-10-13 15:48:16
44	WMS · Sales and fulfilment user	web	2026-10-13 15:48:16	2026-10-13 15:48:16
45	WMS · Finance officer	web	2026-10-13 15:48:17	2026-10-13 15:48:17
46	WMS · Quality officer	web	2026-10-13 15:48:17	2026-10-13 15:48:17
47	WMS · Auditor	web	2026-10-13 15:48:17	2026-10-13 15:48:17
48	WMS · Third-party logistics operator	web	2026-10-13 15:48:17	2026-10-13 15:48:17
49	WMS · Read-only reporting user	web	2026-10-13 15:48:17	2026-10-13 15:48:17
50	Tax Administrator	web	2026-10-14 11:01:51	2026-10-14 11:01:51
51	Tax Preparer	web	2026-10-14 11:01:51	2026-10-14 11:01:51
52	Tax Reviewer	web	2026-10-14 11:01:51	2026-10-14 11:01:51
53	Tax Approver	web	2026-10-14 11:01:51	2026-10-14 11:01:51
54	Tax Compliance Officer	web	2026-10-14 11:01:51	2026-10-14 11:01:51
55	Tax Auditor	web	2026-10-14 11:01:51	2026-10-14 11:01:51
56	Workflow Administrator	web	2026-10-14 19:56:41	2026-10-14 19:56:41
57	Workflow Designer	web	2026-10-14 19:56:41	2026-10-14 19:56:41
58	Workflow Publisher	web	2026-10-14 19:56:41	2026-10-14 19:56:41
59	Process Manager	web	2026-10-14 19:56:42	2026-10-14 19:56:42
60	Workflow Auditor	web	2026-10-14 19:56:42	2026-10-14 19:56:42
61	Workflow Executive	web	2026-10-14 19:56:42	2026-10-14 19:56:42
62	Workflow User	web	2026-10-14 19:56:42	2026-10-14 19:56:42
63	E2E Finance 203139	web	2026-10-14 20:31:39	2026-10-14 20:31:39
64	Manufacturing demo · Production Manager	web	2026-10-21 17:09:12	2026-10-21 17:09:12
65	Manufacturing demo · Plant Director (approver)	web	2026-10-21 17:09:13	2026-10-21 17:09:13
66	Manufacturing demo · Production Planner	web	2026-10-21 17:09:13	2026-10-21 17:09:13
67	Manufacturing demo · Process Engineer	web	2026-10-21 17:09:14	2026-10-21 17:09:14
68	Manufacturing demo · Quality Lead	web	2026-10-21 17:09:14	2026-10-21 17:09:14
69	Manufacturing demo · Maintenance Planner	web	2026-10-21 17:09:14	2026-10-21 17:09:14
70	Manufacturing demo · Machine Operator	web	2026-10-21 17:09:15	2026-10-21 17:09:15
71	Enclosures demo · Finance Manager (approver)	web	2026-10-21 21:26:08	2026-10-21 21:26:08
72	Enclosures demo · Accountant	web	2026-10-21 21:26:09	2026-10-21 21:26:09
73	Enclosures demo · Production Supervisor (laser & bending)	web	2026-10-21 21:26:09	2026-10-21 21:26:09
74	Enclosures demo · Production Supervisor (welding & paint)	web	2026-10-21 21:26:10	2026-10-21 21:26:10
75	Enclosures demo · Production Manager	web	2026-10-21 21:26:10	2026-10-21 21:26:10
76	Enclosures demo · Materials Planner	web	2026-10-21 21:26:10	2026-10-21 21:26:10
77	Enclosures demo · Stock Controller	web	2026-10-21 21:26:11	2026-10-21 21:26:11
78	Enclosures demo · Sales Executive	web	2026-10-21 21:26:11	2026-10-21 21:26:11
79	Ikhwezi demo · supply chain	web	2026-10-25 15:14:02	2026-10-25 15:14:02
80	Ikhwezi demo · Budget holder (approver)	web	2026-10-25 15:14:10	2026-10-25 15:14:10
81	Ikhwezi demo · Head of Supply Chain (approver)	web	2026-10-25 15:14:10	2026-10-25 15:14:10
82	Ikhwezi demo · CFO (approver)	web	2026-10-25 15:14:10	2026-10-25 15:14:10
83	Ikhwezi demo · Accounting Officer (approver)	web	2026-10-25 15:14:10	2026-10-25 15:14:10
\.


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 83, true);


--
-- PostgreSQL database dump complete
--


--
-- PostgreSQL database dump
--


-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permissions (id, name, guard_name, created_at, updated_at) FROM stdin;
1	view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
2	modify	web	2026-07-12 17:44:38	2026-07-12 17:44:38
3	delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
4	leave.ai.basic	web	2026-07-12 17:44:38	2026-07-12 17:44:38
5	leave.ai.advisor	web	2026-07-12 17:44:38	2026-07-12 17:44:38
6	leave.ai.policy	web	2026-07-12 17:44:38	2026-07-12 17:44:38
7	leave.ai.anomaly	web	2026-07-12 17:44:38	2026-07-12 17:44:38
8	leave.ai.workforce	web	2026-07-12 17:44:38	2026-07-12 17:44:38
9	leave.ai.executive	web	2026-07-12 17:44:38	2026-07-12 17:44:38
10	recruitment.jobs.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
11	recruitment.jobs.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
12	recruitment.jobs.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
13	recruitment.jobs.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
14	recruitment.candidates.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
15	recruitment.candidates.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
16	recruitment.candidates.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
17	recruitment.candidates.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
18	recruitment.candidates.upload_cv	web	2026-07-12 17:44:38	2026-07-12 17:44:38
19	recruitment.candidates.anonymise	web	2026-07-12 17:44:38	2026-07-12 17:44:38
20	recruitment.applications.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
21	recruitment.applications.move	web	2026-07-12 17:44:38	2026-07-12 17:44:38
22	recruitment.applications.withdraw	web	2026-07-12 17:44:38	2026-07-12 17:44:38
23	recruitment.requisitions.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
24	recruitment.requisitions.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
25	recruitment.requisitions.approve	web	2026-07-12 17:44:38	2026-07-12 17:44:38
26	recruitment.shortlists.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
27	recruitment.shortlists.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
28	recruitment.shortlists.approve	web	2026-07-12 17:44:38	2026-07-12 17:44:38
29	recruitment.shortlists.override	web	2026-07-12 17:44:38	2026-07-12 17:44:38
30	recruitment.interviews.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
31	recruitment.interviews.score	web	2026-07-12 17:44:38	2026-07-12 17:44:38
32	attendance.dashboard.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
33	attendance.records.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
34	attendance.records.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
35	attendance.records.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
36	attendance.records.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
37	attendance.records.approve	web	2026-07-12 17:44:38	2026-07-12 17:44:38
38	attendance.statuses.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
39	attendance.statuses.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
40	attendance.statuses.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
41	attendance.statuses.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
42	attendance.shifts.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
43	attendance.shifts.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
44	attendance.shifts.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
45	attendance.shifts.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
46	attendance.calendars.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
47	attendance.calendars.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
48	attendance.calendars.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
49	attendance.calendars.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
50	attendance.settings.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
51	attendance.settings.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
52	attendance.notifications.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
53	attendance.clocking.clock_in	web	2026-07-12 17:44:38	2026-07-12 17:44:38
54	attendance.clocking.clock_out	web	2026-07-12 17:44:38	2026-07-12 17:44:38
55	attendance.clocking.break	web	2026-07-12 17:44:38	2026-07-12 17:44:38
56	attendance.clocking.gps	web	2026-07-12 17:44:38	2026-07-12 17:44:38
57	attendance.clocking.wfh	web	2026-07-12 17:44:38	2026-07-12 17:44:38
58	attendance.clocking.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
59	attendance.clocking.approve	web	2026-07-12 17:44:38	2026-07-12 17:44:38
60	attendance.clocking.missed.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
61	attendance.clocking.missed.detect	web	2026-07-12 17:44:38	2026-07-12 17:44:38
62	attendance.device_logs.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
63	attendance.intelligence.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
64	attendance.intelligence.scan	web	2026-07-12 17:44:38	2026-07-12 17:44:38
65	attendance.intelligence.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
66	attendance.intelligence.ai	web	2026-07-12 17:44:38	2026-07-12 17:44:38
67	attendance.workforce.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
68	attendance.workforce.scan	web	2026-07-12 17:44:38	2026-07-12 17:44:38
69	attendance.workforce.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
70	attendance.workforce.ai	web	2026-07-12 17:44:38	2026-07-12 17:44:38
71	attendance.roster.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
72	attendance.roster.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
73	attendance.roster.ai	web	2026-07-12 17:44:38	2026-07-12 17:44:38
74	attendance.wellness.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
75	attendance.wellness.scan	web	2026-07-12 17:44:38	2026-07-12 17:44:38
76	attendance.wellness.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
77	attendance.wellness.ai	web	2026-07-12 17:44:38	2026-07-12 17:44:38
78	attendance.discipline.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
79	attendance.discipline.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
80	attendance.discipline.ai	web	2026-07-12 17:44:38	2026-07-12 17:44:38
81	attendance.policy.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
82	attendance.policy.scan	web	2026-07-12 17:44:38	2026-07-12 17:44:38
83	attendance.policy.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
84	attendance.policy.ai	web	2026-07-12 17:44:38	2026-07-12 17:44:38
85	attendance.productivity.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
86	attendance.productivity.scan	web	2026-07-12 17:44:38	2026-07-12 17:44:38
87	attendance.productivity.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
88	attendance.productivity.ai	web	2026-07-12 17:44:38	2026-07-12 17:44:38
89	attendance.audit.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
90	attendance.reports.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
91	attendance.reports.export	web	2026-07-12 17:44:38	2026-07-12 17:44:38
92	attendance.reports.schedule	web	2026-07-12 17:44:38	2026-07-12 17:44:38
93	attendance.executive.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
94	assets.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
95	assets.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
96	assets.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
97	assets.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
98	assets.assign	web	2026-07-12 17:44:38	2026-07-12 17:44:38
99	assets.maintenance	web	2026-07-12 17:44:38	2026-07-12 17:44:38
100	assets.incidents	web	2026-07-12 17:44:38	2026-07-12 17:44:38
101	assets.movements	web	2026-07-12 17:44:38	2026-07-12 17:44:38
102	assets.performance	web	2026-07-12 17:44:38	2026-07-12 17:44:38
103	assets.depreciation	web	2026-07-12 17:44:38	2026-07-12 17:44:38
104	assets.exit-clearances	web	2026-07-12 17:44:38	2026-07-12 17:44:38
105	assets.misuse-flags	web	2026-07-12 17:44:38	2026-07-12 17:44:38
106	assets.document-extractions	web	2026-07-12 17:44:38	2026-07-12 17:44:38
107	assets.utilization-reports	web	2026-07-12 17:44:38	2026-07-12 17:44:38
108	assets.compliance-rules	web	2026-07-12 17:44:38	2026-07-12 17:44:38
109	assets.procurement	web	2026-07-12 17:44:38	2026-07-12 17:44:38
110	assets.analytics	web	2026-07-12 17:44:38	2026-07-12 17:44:38
111	assets.settings	web	2026-07-12 17:44:38	2026-07-12 17:44:38
112	assets.settings.makes	web	2026-07-12 17:44:38	2026-07-12 17:44:38
113	fleet.dashboard.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
114	fleet.vehicles.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
115	fleet.vehicles.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
116	fleet.vehicles.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
117	fleet.vehicles.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
118	fleet.vehicles.dispose	web	2026-07-12 17:44:38	2026-07-12 17:44:38
119	fleet.vehicles.approve	web	2026-07-12 17:44:38	2026-07-12 17:44:38
120	fleet.vehicles.import	web	2026-07-12 17:44:38	2026-07-12 17:44:38
121	fleet.drivers.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
122	fleet.drivers.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
123	fleet.drivers.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
124	fleet.drivers.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
125	fleet.drivers.suspend	web	2026-07-12 17:44:38	2026-07-12 17:44:38
126	fleet.bookings.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
127	fleet.bookings.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
128	fleet.bookings.approve	web	2026-07-12 17:44:38	2026-07-12 17:44:38
129	fleet.bookings.collect	web	2026-07-12 17:44:38	2026-07-12 17:44:38
130	fleet.bookings.check-in	web	2026-07-12 17:44:38	2026-07-12 17:44:38
131	fleet.bookings.cancel	web	2026-07-12 17:44:38	2026-07-12 17:44:38
132	fleet.bookings.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
133	fleet.cards.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
134	fleet.cards.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
135	fleet.cards.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
136	fleet.cards.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
137	fleet.cards.transactions.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
138	fleet.cards.transactions.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
139	fleet.cards.transactions.flag	web	2026-07-12 17:44:38	2026-07-12 17:44:38
140	fleet.cards.transactions.reconcile	web	2026-07-12 17:44:38	2026-07-12 17:44:38
141	fleet.documents.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
142	fleet.documents.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
143	fleet.documents.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
144	fleet.documents.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
145	fleet.maintenance.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
146	fleet.maintenance.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
147	fleet.maintenance.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
148	fleet.maintenance.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
149	fleet.fuel.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
150	fleet.fuel.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
151	fleet.fuel.delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
152	fleet.reports.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
153	fleet.reports.export	web	2026-07-12 17:44:38	2026-07-12 17:44:38
154	fleet.reports.schedule	web	2026-07-12 17:44:38	2026-07-12 17:44:38
155	fleet.settings.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
156	fleet.settings.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
157	fleet.ai.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
158	fleet.ai.chat	web	2026-07-12 17:44:38	2026-07-12 17:44:38
159	finance.dashboard.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
160	finance.executive.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
161	finance.claims.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
162	finance.claims.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
163	finance.claims.edit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
164	finance.claims.submit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
165	finance.claims.cancel	web	2026-07-12 17:44:38	2026-07-12 17:44:38
166	finance.claims.approve	web	2026-07-12 17:44:38	2026-07-12 17:44:38
167	finance.claims.reject	web	2026-07-12 17:44:38	2026-07-12 17:44:38
168	finance.claims.pay	web	2026-07-12 17:44:38	2026-07-12 17:44:38
169	finance.claims.payment.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
170	finance.claims.reports.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
171	finance.claims.reports.export	web	2026-07-12 17:44:38	2026-07-12 17:44:38
172	finance.claims.reports.schedule	web	2026-07-12 17:44:38	2026-07-12 17:44:38
173	finance.claims.setup	web	2026-07-12 17:44:38	2026-07-12 17:44:38
174	finance.claims.ai.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
175	finance.budgets.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
176	finance.budgets.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
177	finance.payroll.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
178	finance.payroll.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
179	payroll.runs.create	web	2026-07-12 17:44:38	2026-07-12 17:44:38
180	payroll.runs.calculate	web	2026-07-12 17:44:38	2026-07-12 17:44:38
181	payroll.runs.lock	web	2026-07-12 17:44:38	2026-07-12 17:44:38
182	payroll.runs.submit	web	2026-07-12 17:44:38	2026-07-12 17:44:38
183	payroll.runs.release	web	2026-07-12 17:44:38	2026-07-12 17:44:38
184	payroll.runs.cancel	web	2026-07-12 17:44:38	2026-07-12 17:44:38
185	payroll.anomalies.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
186	payroll.approvals.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
187	payroll.settings.manage	web	2026-07-12 17:44:38	2026-07-12 17:44:38
188	finance.reports.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
189	finance.reports.export	web	2026-07-12 17:44:38	2026-07-12 17:44:38
190	finance.reports.schedule	web	2026-07-12 17:44:38	2026-07-12 17:44:38
191	finance.audit.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
192	system.restoration.view	web	2026-07-12 17:44:38	2026-07-12 17:44:38
193	system.restoration.restore	web	2026-07-12 17:44:38	2026-07-12 17:44:38
194	system.restoration.force_delete	web	2026-07-12 17:44:38	2026-07-12 17:44:38
195	helpdesk.tickets.view	web	2026-07-12 17:44:40	2026-07-12 17:44:40
196	helpdesk.tickets.create	web	2026-07-12 17:44:40	2026-07-12 17:44:40
197	helpdesk.tickets.edit	web	2026-07-12 17:44:40	2026-07-12 17:44:40
198	helpdesk.tickets.delete	web	2026-07-12 17:44:40	2026-07-12 17:44:40
199	helpdesk.tickets.assign	web	2026-07-12 17:44:40	2026-07-12 17:44:40
200	helpdesk.tickets.close	web	2026-07-12 17:44:40	2026-07-12 17:44:40
201	helpdesk.tickets.comment	web	2026-07-12 17:44:40	2026-07-12 17:44:40
202	helpdesk.tickets.internal_note	web	2026-07-12 17:44:40	2026-07-12 17:44:40
203	helpdesk.tickets.export	web	2026-07-12 17:44:40	2026-07-12 17:44:40
204	helpdesk.livechat.view	web	2026-07-12 17:44:40	2026-07-12 17:44:40
205	helpdesk.livechat.respond	web	2026-07-12 17:44:40	2026-07-12 17:44:40
206	helpdesk.livechat.assign	web	2026-07-12 17:44:40	2026-07-12 17:44:40
207	helpdesk.livechat.solve	web	2026-07-12 17:44:40	2026-07-12 17:44:40
208	helpdesk.livechat.delete	web	2026-07-12 17:44:40	2026-07-12 17:44:40
209	helpdesk.livechat.convert_ticket	web	2026-07-12 17:44:40	2026-07-12 17:44:40
210	helpdesk.kb.view	web	2026-07-12 17:44:40	2026-07-12 17:44:40
211	helpdesk.kb.create	web	2026-07-12 17:44:40	2026-07-12 17:44:40
212	helpdesk.kb.edit	web	2026-07-12 17:44:40	2026-07-12 17:44:40
213	helpdesk.kb.delete	web	2026-07-12 17:44:40	2026-07-12 17:44:40
214	helpdesk.kb.publish	web	2026-07-12 17:44:40	2026-07-12 17:44:40
215	helpdesk.settings.view	web	2026-07-12 17:44:40	2026-07-12 17:44:40
216	helpdesk.settings.manage	web	2026-07-12 17:44:40	2026-07-12 17:44:40
217	helpdesk.catalogue.view	web	2026-07-12 17:44:40	2026-07-12 17:44:40
218	helpdesk.catalogue.manage	web	2026-07-12 17:44:40	2026-07-12 17:44:40
219	helpdesk.requests.view	web	2026-07-12 17:44:40	2026-07-12 17:44:40
220	helpdesk.requests.create	web	2026-07-12 17:44:40	2026-07-12 17:44:40
221	helpdesk.requests.edit	web	2026-07-12 17:44:40	2026-07-12 17:44:40
222	helpdesk.requests.approve	web	2026-07-12 17:44:40	2026-07-12 17:44:40
223	helpdesk.requests.fulfill	web	2026-07-12 17:44:40	2026-07-12 17:44:40
224	helpdesk.events.view	web	2026-07-12 17:44:40	2026-07-12 17:44:40
225	helpdesk.events.create	web	2026-07-12 17:44:40	2026-07-12 17:44:40
226	helpdesk.events.manage	web	2026-07-12 17:44:40	2026-07-12 17:44:40
227	helpdesk.releases.view	web	2026-07-12 17:44:40	2026-07-12 17:44:40
228	helpdesk.releases.manage	web	2026-07-12 17:44:40	2026-07-12 17:44:40
229	helpdesk.releases.deploy	web	2026-07-12 17:44:40	2026-07-12 17:44:40
230	helpdesk.cmdb.view	web	2026-07-12 17:44:40	2026-07-12 17:44:40
231	helpdesk.cmdb.create	web	2026-07-12 17:44:40	2026-07-12 17:44:40
232	helpdesk.cmdb.edit	web	2026-07-12 17:44:40	2026-07-12 17:44:40
233	helpdesk.cmdb.delete	web	2026-07-12 17:44:40	2026-07-12 17:44:40
234	tenders.view	web	2026-07-12 17:44:40	2026-07-12 17:44:40
235	tenders.create	web	2026-07-12 17:44:40	2026-07-12 17:44:40
236	tenders.edit	web	2026-07-12 17:44:40	2026-07-12 17:44:40
237	tenders.delete	web	2026-07-12 17:44:40	2026-07-12 17:44:40
238	tenders.manage	web	2026-07-12 17:44:40	2026-07-12 17:44:40
239	lms.pa.view	web	2026-07-12 17:44:40	2026-07-12 17:44:40
240	lms.pa.reports.view	web	2026-07-12 17:44:40	2026-07-12 17:44:40
241	lms.pa.recommend	web	2026-07-12 17:44:40	2026-07-12 17:44:40
242	lms.pa.plans.manage	web	2026-07-12 17:44:40	2026-07-12 17:44:40
243	lms.pa.readiness.compute	web	2026-07-12 17:44:40	2026-07-12 17:44:40
244	governance.board.view	web	2026-07-12 17:44:41	2026-07-12 17:44:41
245	governance.board.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
246	governance.meetings.view	web	2026-07-12 17:44:41	2026-07-12 17:44:41
247	governance.meetings.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
248	governance.resolutions.view	web	2026-07-12 17:44:41	2026-07-12 17:44:41
249	governance.resolutions.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
250	governance.resolutions.approve	web	2026-07-12 17:44:41	2026-07-12 17:44:41
251	governance.compliance.view	web	2026-07-12 17:44:41	2026-07-12 17:44:41
252	governance.compliance.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
253	governance.risk.view	web	2026-07-12 17:44:41	2026-07-12 17:44:41
254	governance.risk.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
255	governance.pack.view	web	2026-07-12 17:44:41	2026-07-12 17:44:41
256	governance.pack.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
257	governance.filings.view	web	2026-07-12 17:44:41	2026-07-12 17:44:41
258	governance.filings.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
259	governance.conflicts.view	web	2026-07-12 17:44:41	2026-07-12 17:44:41
260	governance.conflicts.declare	web	2026-07-12 17:44:41	2026-07-12 17:44:41
261	governance.conflicts.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
262	governance.reports.view	web	2026-07-12 17:44:41	2026-07-12 17:44:41
263	governance.reports.generate	web	2026-07-12 17:44:41	2026-07-12 17:44:41
264	governance.roles.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
265	governance.audit.view	web	2026-07-12 17:44:41	2026-07-12 17:44:41
266	governance.sessions.view	web	2026-07-12 17:44:41	2026-07-12 17:44:41
267	governance.sessions.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
268	governance.mfa.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
269	governance.committees.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
270	governance.directors.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
271	governance.org.manage	web	2026-07-12 17:44:41	2026-07-12 17:44:41
272	governance.resolutions.vote	web	2026-07-12 17:44:41	2026-07-12 17:44:41
273	contracts.view	web	2026-07-12 17:44:43	2026-07-12 17:44:43
274	contracts.create	web	2026-07-12 17:44:43	2026-07-12 17:44:43
275	contracts.update	web	2026-07-12 17:44:43	2026-07-12 17:44:43
276	contracts.delete	web	2026-07-12 17:44:43	2026-07-12 17:44:43
277	contracts.approve	web	2026-07-12 17:44:43	2026-07-12 17:44:43
278	suppliers.view	web	2026-07-12 17:44:43	2026-07-12 17:44:43
279	suppliers.create	web	2026-07-12 17:44:43	2026-07-12 17:44:43
280	suppliers.update	web	2026-07-12 17:44:43	2026-07-12 17:44:43
281	suppliers.delete	web	2026-07-12 17:44:43	2026-07-12 17:44:43
282	suppliers.approve	web	2026-07-12 17:44:43	2026-07-12 17:44:43
283	budgets.view	web	2026-07-12 17:44:43	2026-07-12 17:44:43
284	budgets.create	web	2026-07-12 17:44:43	2026-07-12 17:44:43
285	budgets.update	web	2026-07-12 17:44:43	2026-07-12 17:44:43
286	budgets.delete	web	2026-07-12 17:44:43	2026-07-12 17:44:43
287	budgets.approve	web	2026-07-12 17:44:43	2026-07-12 17:44:43
288	procurement.view	web	2026-07-12 17:44:43	2026-07-12 17:44:43
289	procurement.create	web	2026-07-12 17:44:43	2026-07-12 17:44:43
290	procurement.update	web	2026-07-12 17:44:43	2026-07-12 17:44:43
291	procurement.delete	web	2026-07-12 17:44:43	2026-07-12 17:44:43
292	procurement.approve	web	2026-07-12 17:44:43	2026-07-12 17:44:43
293	marketplace.view	web	2026-07-12 17:44:43	2026-07-12 17:44:43
294	marketplace.order	web	2026-07-12 17:44:43	2026-07-12 17:44:43
295	marketplace.manage	web	2026-07-12 17:44:43	2026-07-12 17:44:43
296	finance.journals.post	web	2026-07-12 17:44:44	2026-07-12 17:44:44
297	finance.payments.approve	web	2026-07-12 17:44:44	2026-07-12 17:44:44
298	finance.writeoff	web	2026-07-12 17:44:44	2026-07-12 17:44:44
299	whistleblower.access	web	2026-07-13 17:42:10	2026-07-13 17:42:10
300	whistleblower.settings.manage	web	2026-07-13 17:42:10	2026-07-13 17:42:10
301	offboarding.manage	web	2026-07-13 18:06:59	2026-07-13 18:06:59
302	boarding.manage	web	2026-07-13 18:18:16	2026-07-13 18:18:16
303	assets.approvals	web	2026-07-14 05:30:42	2026-07-14 05:30:42
304	assets.audits	web	2026-07-14 05:30:42	2026-07-14 05:30:42
305	assets.work-orders	web	2026-07-14 05:30:42	2026-07-14 05:30:42
306	assets.parts	web	2026-07-14 05:30:42	2026-07-14 05:30:42
307	pm.projects.view	web	2026-08-06 09:41:57	2026-08-06 09:41:57
308	pm.projects.create	web	2026-08-06 09:41:57	2026-08-06 09:41:57
309	pm.projects.update	web	2026-08-06 09:41:57	2026-08-06 09:41:57
310	pm.projects.delete	web	2026-08-06 09:41:57	2026-08-06 09:41:57
311	pm.projects.manage	web	2026-08-06 09:41:57	2026-08-06 09:41:57
312	pm.tasks.view	web	2026-08-06 09:41:57	2026-08-06 09:41:57
313	pm.tasks.create	web	2026-08-06 09:41:57	2026-08-06 09:41:57
314	pm.tasks.update	web	2026-08-06 09:41:57	2026-08-06 09:41:57
315	pm.tasks.delete	web	2026-08-06 09:41:57	2026-08-06 09:41:57
316	pm.schedule.manage	web	2026-08-06 09:41:57	2026-08-06 09:41:57
317	pm.evidence.view	web	2026-08-06 09:41:57	2026-08-06 09:41:57
318	pm.evidence.record	web	2026-08-06 09:41:57	2026-08-06 09:41:57
319	pm.evidence.supersede	web	2026-08-06 09:41:57	2026-08-06 09:41:57
320	pm.evidence.verify	web	2026-08-06 09:41:57	2026-08-06 09:41:57
321	pm.derived.override	web	2026-08-06 09:41:57	2026-08-06 09:41:57
322	pm.settings.manage	web	2026-08-06 09:41:57	2026-08-06 09:41:57
323	pm.costs.view	web	2026-08-06 16:32:26	2026-08-06 16:32:26
324	pm.members.manage	web	2026-08-06 16:32:26	2026-08-06 16:32:26
325	sheq.dashboard.view	web	2026-08-06 23:02:32	2026-08-06 23:02:32
326	sheq.hazard.report	web	2026-08-06 23:02:32	2026-08-06 23:02:32
327	sheq.incident.report	web	2026-08-06 23:02:32	2026-08-06 23:02:32
328	sheq.inspection.perform	web	2026-08-06 23:02:32	2026-08-06 23:02:32
329	sheq.action.complete	web	2026-08-06 23:02:32	2026-08-06 23:02:32
330	sheq.permit.request	web	2026-08-06 23:02:32	2026-08-06 23:02:32
331	sheq.incident.investigate	web	2026-08-06 23:02:32	2026-08-06 23:02:32
332	sheq.incident.classify	web	2026-08-06 23:02:32	2026-08-06 23:02:32
333	sheq.hazard.triage	web	2026-08-06 23:02:32	2026-08-06 23:02:32
334	sheq.risk_assessment.author	web	2026-08-06 23:02:32	2026-08-06 23:02:32
335	sheq.permit.approve	web	2026-08-06 23:02:32	2026-08-06 23:02:32
336	sheq.action.verify	web	2026-08-06 23:02:32	2026-08-06 23:02:32
337	sheq.document.manage	web	2026-08-06 23:02:32	2026-08-06 23:02:32
338	sheq.obligation.submit	web	2026-08-06 23:02:32	2026-08-06 23:02:32
339	sheq.report.view	web	2026-08-06 23:02:32	2026-08-06 23:02:32
340	sheq.configure	web	2026-08-06 23:02:32	2026-08-06 23:02:32
341	sheq.pack.manage	web	2026-08-06 23:02:32	2026-08-06 23:02:32
342	sheq.confidential.read	web	2026-08-06 23:02:32	2026-08-06 23:02:32
343	sheq.pii.read	web	2026-08-06 23:02:32	2026-08-06 23:02:32
344	sheq.permit.override	web	2026-08-06 23:02:32	2026-08-06 23:02:32
461	procgov.dashboard.view	web	2026-08-08 03:00:56	2026-08-08 03:00:56
462	procgov.plan.view	web	2026-08-08 03:00:56	2026-08-08 03:00:56
463	procgov.tender.view	web	2026-08-08 03:00:56	2026-08-08 03:00:56
464	procgov.deviation.view	web	2026-08-08 03:00:56	2026-08-08 03:00:56
465	procgov.record.view	web	2026-08-08 03:00:56	2026-08-08 03:00:56
466	procgov.document.view	web	2026-08-08 03:00:56	2026-08-08 03:00:56
467	procgov.report.view	web	2026-08-08 03:00:56	2026-08-08 03:00:56
468	procgov.workload.view	web	2026-08-08 03:00:56	2026-08-08 03:00:56
469	procgov.plan.create	web	2026-08-08 03:00:56	2026-08-08 03:00:56
470	procgov.plan.edit	web	2026-08-08 03:00:56	2026-08-08 03:00:56
471	procgov.plan.submit	web	2026-08-08 03:00:56	2026-08-08 03:00:56
472	procgov.plan.approve	web	2026-08-08 03:00:56	2026-08-08 03:00:56
473	procgov.plan.activate	web	2026-08-08 03:00:56	2026-08-08 03:00:56
474	procgov.tender.create	web	2026-08-08 03:00:56	2026-08-08 03:00:56
475	procgov.tender.edit	web	2026-08-08 03:00:56	2026-08-08 03:00:56
476	procgov.evaluate	web	2026-08-08 03:00:56	2026-08-08 03:00:56
477	procgov.tender.moderate	web	2026-08-08 03:00:56	2026-08-08 03:00:56
478	procgov.tender.recommend	web	2026-08-08 03:00:56	2026-08-08 03:00:56
479	procgov.tender.approve	web	2026-08-08 03:00:56	2026-08-08 03:00:56
480	procgov.committee.manage	web	2026-08-08 03:00:56	2026-08-08 03:00:56
481	procgov.deviation.create	web	2026-08-08 03:00:56	2026-08-08 03:00:56
482	procgov.deviation.edit	web	2026-08-08 03:00:56	2026-08-08 03:00:56
483	procgov.deviation.approve	web	2026-08-08 03:00:56	2026-08-08 03:00:56
484	procgov.record.edit	web	2026-08-08 03:00:56	2026-08-08 03:00:56
485	procgov.document.manage	web	2026-08-08 03:00:56	2026-08-08 03:00:56
486	procgov.report.export	web	2026-08-08 03:00:56	2026-08-08 03:00:56
487	procgov.workload.manage	web	2026-08-08 03:00:56	2026-08-08 03:00:56
488	procgov.audit.view	web	2026-08-08 03:00:56	2026-08-08 03:00:56
489	procgov.configure	web	2026-08-08 03:00:56	2026-08-08 03:00:56
490	procgov.document.confidential	web	2026-08-08 03:02:46	2026-08-08 03:02:46
491	procgov.document.restricted	web	2026-08-08 03:02:46	2026-08-08 03:02:46
492	sheq.contractor.manage	web	2026-08-08 04:12:43	2026-08-08 04:12:43
493	sheq.competency.manage	web	2026-08-08 04:12:43	2026-08-08 04:12:43
494	sheq.compliance.manage	web	2026-08-08 04:12:43	2026-08-08 04:12:43
495	sheq.form.manage	web	2026-08-08 04:12:43	2026-08-08 04:12:43
496	create	web	2026-08-09 12:27:01	2026-08-09 12:27:01
497	update	web	2026-08-09 12:27:01	2026-08-09 12:27:01
503	document.view	web	2026-08-26 23:12:30	2026-08-26 23:12:30
504	document.download	web	2026-08-26 23:12:30	2026-08-26 23:12:30
505	document.upload	web	2026-08-26 23:12:30	2026-08-26 23:12:30
506	document.update	web	2026-08-26 23:12:30	2026-08-26 23:12:30
507	document.replace	web	2026-08-26 23:12:30	2026-08-26 23:12:30
508	document.folder.manage	web	2026-08-26 23:12:30	2026-08-26 23:12:30
509	document.archive	web	2026-08-26 23:12:30	2026-08-26 23:12:30
510	document.restore	web	2026-08-26 23:12:30	2026-08-26 23:12:30
511	document.delete	web	2026-08-26 23:12:30	2026-08-26 23:12:30
512	document.confidential.manage	web	2026-08-26 23:12:30	2026-08-26 23:12:30
513	document.legal_hold	web	2026-08-26 23:12:30	2026-08-26 23:12:30
514	document.activity.view	web	2026-08-26 23:12:30	2026-08-26 23:12:30
515	document.purge	web	2026-08-26 23:12:30	2026-08-26 23:12:30
516	document.settings.manage	web	2026-08-26 23:12:30	2026-08-26 23:12:30
517	document.retention.manage	web	2026-08-29 09:36:12	2026-08-29 09:36:12
518	document.share	web	2026-08-29 10:22:43	2026-08-29 10:22:43
519	sheq.incident.triage	web	2026-09-26 23:36:32	2026-09-26 23:36:32
520	sheq.safety_alert.manage	web	2026-09-26 23:36:32	2026-09-26 23:36:32
521	sheq.injury_case.manage	web	2026-09-26 23:36:32	2026-09-26 23:36:32
522	sheq.all_sites	web	2026-10-10 01:50:48	2026-10-10 01:50:48
523	mfg.dashboard.view	web	2026-10-12 07:56:27	2026-10-12 07:56:27
524	mfg.item.view	web	2026-10-12 07:56:28	2026-10-12 07:56:28
525	mfg.bom.view	web	2026-10-12 07:56:28	2026-10-12 07:56:28
526	mfg.item.manage	web	2026-10-12 07:56:28	2026-10-12 07:56:28
527	mfg.bom.manage	web	2026-10-12 07:56:28	2026-10-12 07:56:28
528	mfg.bom.approve	web	2026-10-12 07:56:28	2026-10-12 07:56:28
529	mfg.bom.obsolete	web	2026-10-12 07:56:28	2026-10-12 07:56:28
530	mfg.substitution.approve	web	2026-10-12 07:56:28	2026-10-12 07:56:28
531	mfg.configure	web	2026-10-12 07:56:28	2026-10-12 07:56:28
532	mfg.factory.all	web	2026-10-12 07:56:28	2026-10-12 07:56:28
533	mfg.routing.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
534	mfg.routing.manage	web	2026-10-13 15:48:09	2026-10-13 15:48:09
535	mfg.routing.approve	web	2026-10-13 15:48:09	2026-10-13 15:48:09
536	mfg.order.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
537	mfg.order.manage	web	2026-10-13 15:48:09	2026-10-13 15:48:09
538	mfg.order.approve	web	2026-10-13 15:48:09	2026-10-13 15:48:09
539	mfg.order.release	web	2026-10-13 15:48:09	2026-10-13 15:48:09
540	mfg.order.close	web	2026-10-13 15:48:09	2026-10-13 15:48:09
541	mfg.order.override	web	2026-10-13 15:48:09	2026-10-13 15:48:09
542	mfg.floor.execute	web	2026-10-13 15:48:09	2026-10-13 15:48:09
543	mfg.material.issue	web	2026-10-13 15:48:09	2026-10-13 15:48:09
544	mfg.floor.supervise	web	2026-10-13 15:48:09	2026-10-13 15:48:09
545	mfg.quality.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
546	mfg.quality.inspect	web	2026-10-13 15:48:09	2026-10-13 15:48:09
547	mfg.quality.dispose	web	2026-10-13 15:48:09	2026-10-13 15:48:09
548	mfg.quality.plan	web	2026-10-13 15:48:09	2026-10-13 15:48:09
549	mfg.quality.ncr	web	2026-10-13 15:48:09	2026-10-13 15:48:09
550	mfg.trace.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
551	mfg.recall.manage	web	2026-10-13 15:48:09	2026-10-13 15:48:09
552	mfg.cost.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
553	mfg.cost.manage	web	2026-10-13 15:48:09	2026-10-13 15:48:09
554	mfg.cost.approve	web	2026-10-13 15:48:09	2026-10-13 15:48:09
555	mfg.maintenance.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
556	mfg.maintenance.manage	web	2026-10-13 15:48:09	2026-10-13 15:48:09
557	mfg.maintenance.release	web	2026-10-13 15:48:09	2026-10-13 15:48:09
558	mfg.plan.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
559	mfg.plan.manage	web	2026-10-13 15:48:09	2026-10-13 15:48:09
560	mfg.plan.approve	web	2026-10-13 15:48:09	2026-10-13 15:48:09
561	mfg.labour.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
562	mfg.labour.manage	web	2026-10-13 15:48:09	2026-10-13 15:48:09
563	mfg.sales.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
564	mfg.sales.commit	web	2026-10-13 15:48:09	2026-10-13 15:48:09
565	mfg.subcontract.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
566	mfg.subcontract.manage	web	2026-10-13 15:48:09	2026-10-13 15:48:09
567	mfg.loss.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
568	mfg.loss.investigate	web	2026-10-13 15:48:09	2026-10-13 15:48:09
569	mfg.loss.approve	web	2026-10-13 15:48:09	2026-10-13 15:48:09
570	mfg.assistant.use	web	2026-10-13 15:48:09	2026-10-13 15:48:09
571	mfg.assistant.act	web	2026-10-13 15:48:09	2026-10-13 15:48:09
572	mfg.finance.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
573	mfg.finance.approve	web	2026-10-13 15:48:09	2026-10-13 15:48:09
574	mfg.finance.adjust	web	2026-10-13 15:48:09	2026-10-13 15:48:09
575	mfg.audit.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
576	mfg.transfer.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
577	mfg.transfer.manage	web	2026-10-13 15:48:09	2026-10-13 15:48:09
578	mfg.integration.view	web	2026-10-13 15:48:09	2026-10-13 15:48:09
579	mfg.integration.manage	web	2026-10-13 15:48:09	2026-10-13 15:48:09
580	wms.view	web	2026-10-13 15:48:15	2026-10-13 15:48:15
581	wms.warehouse.manage	web	2026-10-13 15:48:15	2026-10-13 15:48:15
582	wms.location.manage	web	2026-10-13 15:48:15	2026-10-13 15:48:15
583	wms.location.block	web	2026-10-13 15:48:15	2026-10-13 15:48:15
584	wms.item.manage	web	2026-10-13 15:48:15	2026-10-13 15:48:15
585	wms.item.status	web	2026-10-13 15:48:15	2026-10-13 15:48:15
586	wms.barcode.approve_duplicate	web	2026-10-13 15:48:15	2026-10-13 15:48:15
587	wms.stock.adjust	web	2026-10-13 15:48:15	2026-10-13 15:48:15
588	wms.stock.approve	web	2026-10-13 15:48:15	2026-10-13 15:48:15
589	wms.clients.all	web	2026-10-13 15:48:15	2026-10-13 15:48:15
590	wms.clients.manage	web	2026-10-13 15:48:15	2026-10-13 15:48:15
591	wms.receive	web	2026-10-13 15:48:15	2026-10-13 15:48:15
592	wms.receive.exceptions	web	2026-10-13 15:48:15	2026-10-13 15:48:15
593	wms.supplier_return	web	2026-10-13 15:48:16	2026-10-13 15:48:16
594	wms.move	web	2026-10-13 15:48:16	2026-10-13 15:48:16
595	wms.putaway.override	web	2026-10-13 15:48:16	2026-10-13 15:48:16
596	wms.transfer.approve	web	2026-10-13 15:48:16	2026-10-13 15:48:16
597	wms.replenishment.manage	web	2026-10-13 15:48:16	2026-10-13 15:48:16
598	wms.order.manage	web	2026-10-13 15:48:16	2026-10-13 15:48:16
599	wms.pick	web	2026-10-13 15:48:16	2026-10-13 15:48:16
600	wms.pack	web	2026-10-13 15:48:16	2026-10-13 15:48:16
601	wms.dispatch	web	2026-10-13 15:48:16	2026-10-13 15:48:16
602	wms.count	web	2026-10-13 15:48:16	2026-10-13 15:48:16
603	wms.count.manage	web	2026-10-13 15:48:16	2026-10-13 15:48:16
604	wms.returns	web	2026-10-13 15:48:16	2026-10-13 15:48:16
605	wms.returns.credit	web	2026-10-13 15:48:16	2026-10-13 15:48:16
606	wms.quality	web	2026-10-13 15:48:16	2026-10-13 15:48:16
607	wms.quality.manage	web	2026-10-13 15:48:16	2026-10-13 15:48:16
608	wms.recall	web	2026-10-13 15:48:16	2026-10-13 15:48:16
609	wms.loss	web	2026-10-13 15:48:16	2026-10-13 15:48:16
610	wms.rotation.override	web	2026-10-13 15:48:16	2026-10-13 15:48:16
611	wms.accounting	web	2026-10-13 15:48:16	2026-10-13 15:48:16
612	wms.accounting.approve	web	2026-10-13 15:48:16	2026-10-13 15:48:16
613	wms.investigate	web	2026-10-13 15:48:16	2026-10-13 15:48:16
614	wms.protection.manage	web	2026-10-13 15:48:16	2026-10-13 15:48:16
615	wms.loss.approve	web	2026-10-13 15:48:16	2026-10-13 15:48:16
616	wms.plan	web	2026-10-13 15:48:16	2026-10-13 15:48:16
617	wms.supervise	web	2026-10-13 15:48:16	2026-10-13 15:48:16
618	wms.slotting.approve	web	2026-10-13 15:48:16	2026-10-13 15:48:16
619	wms.integrations.manage	web	2026-10-13 15:48:16	2026-10-13 15:48:16
620	wms.warehouses.all	web	2026-10-13 15:48:16	2026-10-13 15:48:16
621	wms.override	web	2026-10-13 15:48:16	2026-10-13 15:48:16
622	wms.audit.view	web	2026-10-13 15:48:16	2026-10-13 15:48:16
623	tax.view	web	2026-10-14 11:01:50	2026-10-14 11:01:50
624	tax.registration.manage	web	2026-10-14 11:01:50	2026-10-14 11:01:50
625	tax.rule.draft	web	2026-10-14 11:01:50	2026-10-14 11:01:50
626	tax.rule.approve	web	2026-10-14 11:01:50	2026-10-14 11:01:50
627	tax.period.manage	web	2026-10-14 11:01:50	2026-10-14 11:01:50
628	tax.period.reopen	web	2026-10-14 11:01:50	2026-10-14 11:01:50
629	tax.prepare	web	2026-10-14 11:01:50	2026-10-14 11:01:50
630	tax.review	web	2026-10-14 11:01:50	2026-10-14 11:01:50
631	tax.approve	web	2026-10-14 11:01:50	2026-10-14 11:01:50
632	tax.reconcile	web	2026-10-14 11:01:50	2026-10-14 11:01:50
633	tax.reconcile.sign_off	web	2026-10-14 11:01:50	2026-10-14 11:01:50
634	tax.calendar.manage	web	2026-10-14 11:01:50	2026-10-14 11:01:50
635	tax.audit.view	web	2026-10-14 11:01:50	2026-10-14 11:01:50
636	tax.payroll.view	web	2026-10-14 11:01:50	2026-10-14 11:01:50
637	tax.admin	web	2026-10-14 11:01:50	2026-10-14 11:01:50
638	workflow.view	web	2026-10-14 19:56:41	2026-10-14 19:56:41
639	workflow.start	web	2026-10-14 19:56:41	2026-10-14 19:56:41
640	workflow.design	web	2026-10-14 19:56:41	2026-10-14 19:56:41
641	workflow.publish	web	2026-10-14 19:56:41	2026-10-14 19:56:41
642	workflow.manage	web	2026-10-14 19:56:41	2026-10-14 19:56:41
643	workflow.reports	web	2026-10-14 19:56:41	2026-10-14 19:56:41
644	workflow.audit	web	2026-10-14 19:56:41	2026-10-14 19:56:41
645	workflow.admin	web	2026-10-14 19:56:41	2026-10-14 19:56:41
646	mfg.intel.view	web	2026-10-20 21:03:27	2026-10-20 21:03:27
647	mfg.intel.manage	web	2026-10-20 21:03:28	2026-10-20 21:03:28
648	mfg.intel.approve	web	2026-10-20 21:03:28	2026-10-20 21:03:28
649	mfg.intel.configure	web	2026-10-20 21:03:28	2026-10-20 21:03:28
650	mfg.intel.external	web	2026-10-20 21:03:28	2026-10-20 21:03:28
651	mfg.intel.compliance	web	2026-10-20 21:03:28	2026-10-20 21:03:28
652	mfg.energy.view	web	2026-10-20 21:03:28	2026-10-20 21:03:28
653	mfg.energy.manage	web	2026-10-20 21:03:28	2026-10-20 21:03:28
654	mfg.intel.model.manage	web	2026-10-20 22:19:31	2026-10-20 22:19:31
655	mfg.intel.model.deploy	web	2026-10-20 22:19:31	2026-10-20 22:19:31
656	mfg.intel.audit.view	web	2026-10-20 22:19:31	2026-10-20 22:19:31
657	mfg.intel.report.export	web	2026-10-20 22:20:51	2026-10-20 22:20:51
658	procurement.po.manual_override	web	2026-10-21 20:34:19	2026-10-21 20:34:19
659	esign.view	web	2026-10-25 15:14:02	2026-10-25 15:14:02
660	esign.send	web	2026-10-25 15:14:02	2026-10-25 15:14:02
661	ai_studio.view	web	2026-10-27 15:25:47	2026-10-27 15:25:47
662	ai_studio.view_analytics	web	2026-10-27 15:25:47	2026-10-27 15:25:47
663	ai_studio.edit_content	web	2026-10-27 15:25:47	2026-10-27 15:25:47
664	ai_studio.create_campaigns	web	2026-10-27 15:25:47	2026-10-27 15:25:47
665	ai_studio.generate_images	web	2026-10-27 15:25:47	2026-10-27 15:25:47
666	ai_studio.generate_videos	web	2026-10-27 15:25:47	2026-10-27 15:25:47
667	ai_studio.schedule_content	web	2026-10-27 15:25:47	2026-10-27 15:25:47
668	ai_studio.manage_brands	web	2026-10-27 15:25:47	2026-10-27 15:25:47
669	ai_studio.approve_content	web	2026-10-27 15:25:47	2026-10-27 15:25:47
670	ai_studio.publish_content	web	2026-10-27 15:25:47	2026-10-27 15:25:47
671	ai_studio.view_usage	web	2026-10-27 15:25:47	2026-10-27 15:25:47
672	ai_studio.manage_knowledge	web	2026-10-27 15:25:47	2026-10-27 15:25:47
673	ai_studio.manage_avatars	web	2026-10-27 15:25:47	2026-10-27 15:25:47
674	ai_studio.manage_consent	web	2026-10-27 15:25:47	2026-10-27 15:25:47
675	ai_studio.manage_social_accounts	web	2026-10-27 15:25:47	2026-10-27 15:25:47
676	ai_studio.manage_autopilot	web	2026-10-27 15:25:47	2026-10-27 15:25:47
677	ai_studio.manage_integrations	web	2026-10-27 15:25:47	2026-10-27 15:25:47
678	ai_studio.manage_settings	web	2026-10-27 15:25:47	2026-10-27 15:25:47
679	ai_studio.view_audit_logs	web	2026-10-27 15:25:47	2026-10-27 15:25:47
680	ai_studio.manage_billing	web	2026-10-27 15:25:47	2026-10-27 15:25:47
\.


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permissions_id_seq', 680, true);


--
-- PostgreSQL database dump complete
--


--
-- PostgreSQL database dump
--


-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: role_has_permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.role_has_permissions (permission_id, role_id) FROM stdin;
1	4
1	5
2	5
32	5
33	5
42	5
38	5
46	5
53	5
54	5
55	5
56	5
57	5
63	5
67	5
71	5
74	5
78	5
1	6
2	6
3	6
32	6
33	6
42	6
38	6
46	6
53	6
54	6
55	6
56	6
57	6
34	6
35	6
58	6
59	6
60	6
61	6
63	6
64	6
65	6
67	6
68	6
69	6
71	6
72	6
74	6
75	6
76	6
78	6
79	6
1	7
2	7
3	7
10	7
11	7
12	7
14	7
15	7
16	7
18	7
20	7
21	7
22	7
23	7
24	7
25	7
26	7
27	7
28	7
29	7
30	7
31	7
32	7
33	7
42	7
38	7
46	7
53	7
54	7
55	7
56	7
57	7
34	7
35	7
58	7
59	7
60	7
61	7
37	7
36	7
39	7
40	7
43	7
44	7
47	7
48	7
63	7
64	7
65	7
67	7
68	7
69	7
71	7
72	7
74	7
75	7
76	7
78	7
79	7
81	7
82	7
83	7
85	7
86	7
87	7
89	7
90	7
91	7
93	7
66	7
70	7
73	7
77	7
80	7
94	7
110	7
98	7
99	7
100	7
101	7
102	7
1	8
2	8
3	8
10	8
11	8
12	8
13	8
14	8
15	8
16	8
17	8
18	8
19	8
20	8
21	8
22	8
23	8
24	8
25	8
26	8
27	8
28	8
29	8
30	8
31	8
32	8
33	8
42	8
38	8
46	8
53	8
54	8
55	8
56	8
57	8
34	8
35	8
58	8
59	8
60	8
61	8
37	8
36	8
39	8
40	8
43	8
44	8
47	8
48	8
63	8
64	8
65	8
67	8
68	8
69	8
71	8
72	8
74	8
75	8
76	8
78	8
79	8
81	8
82	8
83	8
85	8
86	8
87	8
89	8
90	8
91	8
93	8
41	8
45	8
49	8
50	8
51	8
52	8
62	8
66	8
70	8
73	8
77	8
80	8
84	8
88	8
92	8
94	8
110	8
98	8
99	8
100	8
101	8
102	8
95	8
96	8
103	8
104	8
105	8
106	8
107	8
108	8
109	8
97	8
111	8
112	8
1	9
2	9
10	9
11	9
12	9
14	9
15	9
16	9
18	9
20	9
21	9
22	9
23	9
24	9
25	9
26	9
27	9
28	9
29	9
30	9
31	9
32	9
33	9
42	9
38	9
46	9
53	9
54	9
55	9
56	9
57	9
34	9
35	9
58	9
59	9
60	9
61	9
37	9
36	9
39	9
40	9
43	9
44	9
47	9
48	9
63	9
64	9
65	9
67	9
68	9
69	9
71	9
72	9
74	9
75	9
76	9
78	9
79	9
81	9
82	9
83	9
85	9
86	9
87	9
89	9
90	9
91	9
93	9
41	9
45	9
49	9
50	9
51	9
52	9
62	9
66	9
70	9
73	9
77	9
80	9
84	9
88	9
92	9
10	10
11	10
12	10
13	10
14	10
15	10
16	10
17	10
18	10
19	10
20	10
21	10
22	10
23	10
24	10
25	10
26	10
27	10
28	10
29	10
30	10
31	10
1	11
2	11
3	11
32	11
33	11
42	11
38	11
46	11
53	11
54	11
55	11
56	11
57	11
63	11
67	11
71	11
74	11
78	11
79	11
1	12
2	12
3	12
32	12
33	12
42	12
38	12
46	12
53	12
54	12
55	12
56	12
57	12
34	12
35	12
58	12
59	12
60	12
61	12
63	12
67	12
71	12
74	12
78	12
1	13
2	13
3	13
10	13
11	13
12	13
14	13
15	13
16	13
18	13
20	13
21	13
22	13
23	13
24	13
25	13
26	13
27	13
28	13
29	13
30	13
31	13
32	13
33	13
42	13
38	13
46	13
53	13
54	13
55	13
56	13
57	13
34	13
35	13
58	13
59	13
60	13
61	13
37	13
36	13
39	13
40	13
43	13
44	13
47	13
48	13
63	13
64	13
65	13
67	13
68	13
69	13
71	13
72	13
74	13
75	13
76	13
78	13
79	13
81	13
82	13
83	13
85	13
86	13
87	13
89	13
90	13
91	13
93	13
66	13
70	13
73	13
77	13
80	13
1	14
2	14
3	14
10	14
11	14
12	14
14	14
15	14
16	14
18	14
20	14
21	14
22	14
23	14
24	14
25	14
26	14
27	14
28	14
29	14
30	14
31	14
32	14
33	14
42	14
38	14
46	14
53	14
54	14
55	14
56	14
57	14
63	14
64	14
65	14
66	14
67	14
68	14
69	14
70	14
71	14
74	14
78	14
1	15
2	15
3	15
4	15
5	15
6	15
7	15
8	15
9	15
10	15
11	15
12	15
13	15
14	15
15	15
16	15
17	15
18	15
19	15
20	15
21	15
22	15
23	15
24	15
25	15
26	15
27	15
28	15
29	15
30	15
31	15
32	15
33	15
34	15
35	15
36	15
37	15
38	15
39	15
40	15
41	15
42	15
43	15
44	15
45	15
46	15
47	15
48	15
49	15
50	15
51	15
52	15
53	15
54	15
55	15
56	15
57	15
58	15
59	15
60	15
61	15
62	15
63	15
64	15
65	15
66	15
67	15
68	15
69	15
70	15
71	15
72	15
73	15
74	15
75	15
76	15
77	15
78	15
79	15
80	15
81	15
82	15
83	15
84	15
85	15
86	15
87	15
88	15
89	15
90	15
91	15
92	15
93	15
94	15
95	15
96	15
97	15
98	15
99	15
100	15
101	15
102	15
103	15
104	15
105	15
106	15
107	15
108	15
109	15
110	15
111	15
112	15
113	15
114	15
115	15
116	15
117	15
118	15
119	15
120	15
121	15
122	15
123	15
124	15
125	15
126	15
127	15
128	15
129	15
130	15
131	15
132	15
133	15
134	15
135	15
136	15
137	15
138	15
139	15
140	15
141	15
142	15
143	15
144	15
145	15
146	15
147	15
148	15
149	15
150	15
151	15
152	15
153	15
154	15
155	15
156	15
157	15
158	15
159	15
160	15
161	15
162	15
163	15
164	15
165	15
166	15
167	15
168	15
169	15
170	15
171	15
172	15
173	15
174	15
175	15
176	15
177	15
178	15
179	15
180	15
181	15
182	15
183	15
184	15
185	15
186	15
187	15
188	15
189	15
190	15
191	15
1	16
2	16
3	16
32	16
33	16
42	16
38	16
46	16
53	16
54	16
55	16
56	16
57	16
159	16
161	16
162	16
163	16
164	16
165	16
169	16
168	16
170	16
171	16
177	16
188	16
189	16
191	16
166	16
167	16
172	16
173	16
174	16
160	16
175	16
176	16
178	16
190	16
1	17
2	17
32	17
33	17
42	17
38	17
46	17
53	17
54	17
55	17
56	17
57	17
159	17
161	17
162	17
163	17
164	17
165	17
169	17
168	17
170	17
171	17
177	17
188	17
189	17
191	17
1	18
32	18
33	18
42	18
38	18
46	18
53	18
54	18
55	18
56	18
57	18
159	18
161	18
162	18
163	18
164	18
165	18
1	19
2	19
32	19
33	19
42	19
38	19
46	19
53	19
54	19
55	19
56	19
57	19
159	19
161	19
169	19
168	19
177	19
178	19
188	19
189	19
191	19
1	20
2	20
3	20
32	20
33	20
42	20
38	20
46	20
53	20
54	20
55	20
56	20
57	20
159	20
161	20
162	20
163	20
164	20
165	20
166	20
167	20
160	20
175	20
176	20
170	20
171	20
188	20
189	20
191	20
4	4
4	5
4	6
4	7
4	8
4	9
4	11
4	12
4	13
4	14
4	16
4	17
4	18
4	19
4	20
5	5
5	6
5	7
5	8
5	9
5	11
5	12
5	13
5	14
5	16
5	17
5	19
5	20
6	6
6	7
6	8
6	9
6	11
6	12
6	13
6	14
6	16
6	20
7	9
7	11
7	13
7	14
7	16
8	7
8	9
8	12
8	13
8	14
8	16
8	20
9	13
9	14
9	16
9	20
239	21
240	21
241	21
242	21
243	21
239	7
240	7
239	13
240	13
239	14
240	14
239	9
240	9
239	8
240	8
239	15
240	15
241	9
242	9
243	9
241	8
242	8
243	8
241	15
242	15
243	15
244	22
245	22
246	22
247	22
248	22
249	22
250	22
251	22
252	22
253	22
254	22
255	22
256	22
257	22
258	22
259	22
260	22
261	22
262	22
263	22
264	22
265	22
266	22
267	22
268	22
269	22
270	22
271	22
272	22
244	23
245	23
246	23
247	23
248	23
249	23
272	23
251	23
252	23
253	23
254	23
255	23
256	23
257	23
258	23
259	23
260	23
261	23
262	23
263	23
265	23
266	23
267	23
269	23
270	23
244	24
246	24
247	24
248	24
250	24
272	24
251	24
253	24
255	24
259	24
260	24
262	24
263	24
266	24
244	25
246	25
248	25
250	25
272	25
251	25
253	25
255	25
259	25
260	25
262	25
266	25
244	26
246	26
248	26
272	26
251	26
253	26
254	26
255	26
259	26
260	26
266	26
244	27
246	27
248	27
272	27
251	27
253	27
255	27
257	27
259	27
262	27
263	27
265	27
266	27
267	27
244	28
246	28
248	28
255	28
266	28
1	29
2	29
1	30
2	30
3	30
1	31
2	31
3	31
273	15
274	15
275	15
276	15
277	15
278	15
279	15
280	15
281	15
282	15
283	15
284	15
285	15
286	15
287	15
288	15
289	15
290	15
291	15
292	15
293	15
294	15
295	15
273	8
274	8
275	8
276	8
277	8
278	8
279	8
280	8
281	8
282	8
283	8
284	8
285	8
286	8
287	8
288	8
289	8
290	8
291	8
292	8
293	8
294	8
295	8
296	8
296	15
296	9
296	16
296	17
296	18
296	20
296	19
297	8
298	8
297	15
298	15
297	16
298	16
297	20
298	20
299	15
300	15
299	8
300	8
301	15
301	8
302	15
302	8
113	1
114	1
115	1
116	1
117	1
118	1
119	1
120	1
121	1
122	1
123	1
124	1
125	1
126	1
127	1
128	1
129	1
130	1
131	1
132	1
133	1
134	1
135	1
136	1
137	1
138	1
139	1
140	1
141	1
142	1
143	1
144	1
145	1
146	1
147	1
148	1
149	1
150	1
151	1
152	1
153	1
154	1
155	1
156	1
157	1
158	1
113	2
114	2
126	2
127	2
129	2
130	2
149	2
150	2
113	3
114	3
121	3
126	3
133	3
137	3
141	3
145	3
149	3
152	3
304	7
305	7
306	7
304	8
305	8
306	8
303	8
195	15
196	15
197	15
198	15
199	15
200	15
201	15
202	15
203	15
204	15
205	15
206	15
207	15
208	15
209	15
210	15
211	15
212	15
213	15
214	15
215	15
216	15
217	15
218	15
219	15
220	15
221	15
222	15
223	15
224	15
225	15
226	15
227	15
228	15
229	15
230	15
231	15
232	15
233	15
234	15
235	15
236	15
237	15
238	15
244	15
245	15
246	15
247	15
248	15
249	15
250	15
251	15
252	15
253	15
254	15
255	15
256	15
257	15
258	15
259	15
260	15
261	15
262	15
263	15
264	15
265	15
266	15
267	15
268	15
269	15
270	15
271	15
272	15
303	15
304	15
305	15
306	15
307	15
308	15
309	15
310	15
311	15
312	15
313	15
314	15
315	15
316	15
317	15
318	15
319	15
320	15
321	15
322	15
307	8
308	8
309	8
310	8
311	8
312	8
313	8
314	8
315	8
316	8
317	8
318	8
319	8
320	8
321	8
322	8
323	15
324	15
323	8
324	8
325	15
326	15
327	15
328	15
329	15
330	15
331	15
332	15
333	15
334	15
335	15
336	15
337	15
338	15
339	15
340	15
341	15
342	15
343	15
344	15
325	8
326	8
327	8
328	8
329	8
330	8
331	8
332	8
333	8
334	8
335	8
336	8
337	8
338	8
339	8
340	8
341	8
342	8
343	8
344	8
273	79
274	79
275	79
276	79
277	79
278	79
279	79
280	79
281	79
282	79
283	79
284	79
285	79
286	79
287	79
288	79
289	79
290	79
291	79
292	79
293	79
294	79
295	79
461	79
462	79
463	79
464	79
465	79
466	79
467	79
468	79
469	79
470	79
471	79
472	79
473	79
474	79
475	79
476	79
477	79
478	79
479	79
480	79
481	79
482	79
483	79
484	79
485	79
486	79
487	79
488	79
489	79
490	79
491	79
658	79
1	79
496	79
497	79
3	79
659	79
660	79
638	79
461	15
462	15
463	15
464	15
465	15
466	15
467	15
468	15
469	15
470	15
471	15
472	15
473	15
474	15
475	15
476	15
477	15
478	15
479	15
480	15
481	15
482	15
483	15
484	15
485	15
486	15
487	15
488	15
489	15
461	8
462	8
463	8
464	8
465	8
466	8
467	8
468	8
469	8
470	8
471	8
472	8
473	8
474	8
475	8
476	8
477	8
478	8
479	8
480	8
481	8
482	8
483	8
484	8
485	8
486	8
487	8
488	8
489	8
490	15
491	15
490	8
491	8
492	15
493	15
494	15
495	15
492	8
493	8
494	8
495	8
307	35
308	35
309	35
311	35
312	35
313	35
314	35
316	35
317	35
324	35
323	35
322	35
503	15
504	15
505	15
506	15
507	15
508	15
509	15
510	15
511	15
512	15
513	15
514	15
515	15
516	15
503	8
504	8
505	8
506	8
507	8
508	8
509	8
510	8
511	8
512	8
513	8
514	8
515	8
516	8
517	15
517	8
518	15
518	8
1	36
4	36
1	37
4	37
519	15
520	15
521	15
519	8
520	8
521	8
523	15
524	15
525	15
526	15
527	15
528	15
529	15
530	15
531	15
532	15
523	8
524	8
525	8
526	8
527	8
528	8
529	8
530	8
531	8
532	8
533	15
534	15
535	15
536	15
537	15
538	15
539	15
540	15
541	15
542	15
543	15
544	15
545	15
546	15
547	15
548	15
549	15
550	15
551	15
552	15
553	15
554	15
555	15
556	15
557	15
558	15
559	15
560	15
561	15
562	15
563	15
564	15
565	15
566	15
567	15
568	15
569	15
570	15
571	15
572	15
573	15
574	15
575	15
576	15
577	15
578	15
579	15
533	8
534	8
535	8
536	8
537	8
538	8
539	8
540	8
541	8
542	8
543	8
544	8
545	8
546	8
547	8
548	8
549	8
550	8
551	8
552	8
553	8
554	8
555	8
556	8
557	8
558	8
559	8
560	8
561	8
562	8
563	8
564	8
565	8
566	8
567	8
568	8
569	8
570	8
571	8
572	8
573	8
574	8
575	8
576	8
577	8
578	8
579	8
580	15
581	15
582	15
583	15
584	15
585	15
586	15
587	15
588	15
589	15
590	15
591	15
592	15
593	15
594	15
595	15
596	15
597	15
598	15
599	15
600	15
601	15
602	15
603	15
604	15
605	15
606	15
607	15
608	15
609	15
610	15
611	15
612	15
613	15
614	15
615	15
616	15
617	15
618	15
619	15
620	15
621	15
622	15
580	8
581	8
582	8
583	8
584	8
585	8
586	8
587	8
588	8
589	8
590	8
591	8
592	8
593	8
594	8
595	8
596	8
597	8
598	8
599	8
600	8
601	8
602	8
603	8
604	8
605	8
606	8
607	8
608	8
609	8
610	8
611	8
612	8
613	8
614	8
615	8
616	8
617	8
618	8
619	8
620	8
621	8
622	8
580	38
581	38
582	38
583	38
584	38
585	38
586	38
587	38
588	38
589	38
590	38
591	38
592	38
593	38
594	38
595	38
596	38
597	38
598	38
599	38
600	38
601	38
602	38
603	38
604	38
605	38
606	38
607	38
608	38
609	38
610	38
611	38
612	38
613	38
614	38
615	38
616	38
617	38
618	38
620	38
621	38
622	38
580	39
591	39
594	39
593	39
606	39
580	40
587	40
588	40
602	40
603	40
594	40
583	40
585	40
597	40
609	40
595	40
596	40
580	41
599	41
600	41
594	41
602	41
580	42
600	42
601	42
580	43
616	43
593	43
592	43
580	44
598	44
604	44
580	45
611	45
612	45
605	45
615	45
580	46
606	46
607	46
608	46
585	46
580	47
622	47
580	48
591	48
594	48
599	48
600	48
601	48
602	48
580	49
623	15
624	15
625	15
626	15
627	15
628	15
629	15
630	15
631	15
632	15
633	15
634	15
635	15
636	15
637	15
623	50
624	50
625	50
627	50
634	50
635	50
637	50
623	51
629	51
632	51
625	51
623	52
630	52
632	52
633	52
635	52
623	53
631	53
626	53
633	53
628	53
635	53
623	54
634	54
635	54
623	55
635	55
638	15
639	15
640	15
641	15
642	15
643	15
644	15
645	15
638	56
639	56
640	56
642	56
643	56
644	56
645	56
638	57
639	57
640	57
638	58
639	58
641	58
638	59
639	59
642	59
643	59
638	60
644	60
643	60
638	61
643	61
638	62
639	62
646	15
647	15
648	15
649	15
650	15
651	15
652	15
653	15
646	8
647	8
648	8
649	8
650	8
651	8
652	8
653	8
654	15
655	15
656	15
654	8
655	8
656	8
657	15
657	8
523	64
524	64
525	64
526	64
527	64
529	64
533	64
534	64
536	64
537	64
539	64
540	64
541	64
542	64
543	64
544	64
545	64
546	64
547	64
548	64
549	64
550	64
551	64
552	64
553	64
555	64
556	64
557	64
558	64
559	64
561	64
562	64
563	64
564	64
565	64
566	64
567	64
568	64
646	64
647	64
654	64
656	64
657	64
650	64
651	64
652	64
653	64
570	64
571	64
572	64
574	64
575	64
576	64
577	64
578	64
579	64
531	64
532	64
523	65
524	65
525	65
533	65
536	65
558	65
545	65
555	65
552	65
646	65
650	65
652	65
570	65
550	65
567	65
532	65
528	65
535	65
538	65
554	65
560	65
648	65
569	65
530	65
573	65
649	65
655	65
539	65
540	65
647	65
651	65
561	65
562	65
656	65
572	65
575	65
657	65
523	66
524	66
525	66
533	66
536	66
558	66
545	66
555	66
552	66
646	66
650	66
652	66
570	66
550	66
567	66
532	66
559	66
537	66
539	66
564	66
563	66
647	66
561	66
571	66
523	67
524	67
525	67
533	67
536	67
558	67
545	67
555	67
552	67
646	67
650	67
652	67
570	67
550	67
567	67
532	67
526	67
527	67
534	67
553	67
548	67
654	67
647	67
523	68
524	68
525	68
533	68
536	68
558	68
545	68
555	68
552	68
646	68
650	68
652	68
570	68
550	68
567	68
532	68
546	68
547	68
548	68
549	68
551	68
568	68
647	68
651	68
523	69
524	69
525	69
533	69
536	69
558	69
545	69
555	69
552	69
646	69
650	69
652	69
570	69
550	69
567	69
532	69
556	69
557	69
653	69
579	69
578	69
647	69
523	70
536	70
542	70
543	70
532	70
1	71
496	71
497	71
159	71
188	71
189	71
296	71
191	71
175	71
3	71
297	71
298	71
176	71
288	71
292	71
283	71
287	71
278	71
523	71
536	71
552	71
554	71
572	71
573	71
563	71
532	71
580	71
620	71
603	71
588	71
611	71
612	71
1	72
496	72
497	72
159	72
188	72
189	72
296	72
191	72
175	72
288	72
278	72
283	72
523	72
536	72
552	72
572	72
563	72
532	72
580	72
620	72
611	72
523	73
524	73
525	73
533	73
536	73
558	73
545	73
552	73
550	73
567	73
565	73
563	73
646	73
570	73
532	73
1	73
542	73
543	73
544	73
546	73
647	73
561	73
555	73
576	73
580	73
620	73
523	74
524	74
525	74
533	74
536	74
558	74
545	74
552	74
550	74
567	74
565	74
563	74
646	74
570	74
532	74
1	74
542	74
543	74
544	74
546	74
566	74
647	74
561	74
555	74
576	74
580	74
620	74
523	75
524	75
525	75
526	75
527	75
528	75
529	75
530	75
533	75
534	75
535	75
536	75
537	75
538	75
539	75
540	75
541	75
542	75
543	75
544	75
545	75
546	75
547	75
548	75
549	75
550	75
551	75
552	75
553	75
554	75
555	75
556	75
557	75
558	75
559	75
560	75
561	75
562	75
563	75
564	75
565	75
566	75
567	75
568	75
569	75
646	75
647	75
648	75
649	75
654	75
655	75
656	75
657	75
650	75
651	75
652	75
653	75
570	75
571	75
572	75
573	75
574	75
575	75
576	75
577	75
578	75
579	75
531	75
532	75
1	75
288	75
580	75
620	75
523	76
524	76
525	76
533	76
536	76
558	76
545	76
552	76
550	76
567	76
565	76
563	76
646	76
570	76
532	76
1	76
496	76
526	76
527	76
534	76
553	76
559	76
537	76
539	76
564	76
566	76
577	76
576	76
647	76
288	76
289	76
290	76
278	76
283	76
580	76
620	76
616	76
1	77
496	77
497	77
523	77
524	77
536	77
550	77
543	77
576	77
532	77
288	77
289	77
278	77
580	77
620	77
602	77
591	77
594	77
587	77
582	77
584	77
606	77
609	77
593	77
1	78
496	78
497	78
159	78
523	78
524	78
536	78
563	78
564	78
558	78
532	78
\.


--
-- PostgreSQL database dump complete
--


--
-- PostgreSQL database dump
--


-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: billing_modules; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.billing_modules (id, code, name, category, price_monthly, is_sellable, is_active, sort, created_at, updated_at, websites_per_month, backlinks_per_month, entities_included) FROM stdin;
1	user_management	User Management	Core	0.00	f	t	0	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
2	roles_permissions	Roles & Permissions	Core	0.00	f	t	1	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
3	reports	Reports	Core	0.00	f	t	2	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
4	company_settings	Company Settings	Core	0.00	f	t	3	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
5	system_settings	System Settings	Core	0.00	f	t	4	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
34	recognition	Recognition	Culture	99.00	t	t	33	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
18	petty_cash	Petty Cash	Finance	99.00	t	t	17	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
16	claims	Claims	Finance	149.00	t	t	15	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
17	staff_loans	Staff Loans	Finance	149.00	t	t	16	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
20	budget_management	Budget Management	Finance	299.00	t	t	19	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
35	policy_enforcement	Policy Enforcement	Governance	199.00	t	t	34	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
36	popia_compliance	Popia Compliance	Governance	199.00	t	t	35	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
38	whistleblower	Whistleblower	Governance	249.00	t	t	37	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
37	board_committee	Board Committee	Governance	299.00	t	t	36	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
13	offboarding	Offboarding	HR & People	99.00	t	t	12	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
6	leave_management	Leave Management	HR & People	149.00	t	t	5	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
7	time_attendance	Time Attendance	HR & People	149.00	t	t	6	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
15	disciplinary	Disciplinary	HR & People	149.00	t	t	14	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
11	recruitment	Recruitment	HR & People	299.00	t	t	10	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
10	performance_appraisal	Performance Appraisal	HR & People	299.00	t	t	9	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
14	learning_management	Learning Management	HR & People	599.00	t	t	13	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
8	payroll	Payroll	HR & People	599.00	t	t	7	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
9	payroll_bureau	Payroll Bureau	HR & People	999.00	t	t	8	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
28	resources	Resources	Operations	99.00	t	t	27	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
26	marketplace	Marketplace	Operations	149.00	t	t	25	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
21	procurement_requests	Procurement Requests	Operations	199.00	t	t	20	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
22	supplier_management	Supplier Management	Operations	299.00	t	t	21	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
23	contract_management	Contract Management	Operations	299.00	t	t	22	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
24	asset_management	Asset Management	Operations	299.00	t	t	23	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
25	fleet_management	Fleet Management	Operations	599.00	t	t	24	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
27	tender_pursuit	Tender Pursuit	Operations	299.00	t	t	26	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
31	surveys	Surveys	Sales & Service	99.00	t	t	30	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
32	conferences	Conferences	Sales & Service	249.00	t	t	31	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
30	help_desk	Help Desk	Sales & Service	199.00	t	t	29	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
33	content_management	Content Management	Sales & Service	199.00	t	t	32	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
29	crm	Crm	Sales & Service	599.00	t	t	28	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
12	boarding	Onboarding	HR & People	99.00	t	t	11	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	\N
40	employee_reputation	Employee Reputation & Risk	HR & People	299.00	t	t	15	2026-07-24 20:46:45	2026-07-24 20:46:45	\N	\N	\N
42	sheq	SHEQ	Operations	450.00	t	t	52	2026-08-06 23:02:33	2026-08-06 23:02:33	\N	\N	\N
41	project_management	Project Management	Operations	450.00	t	t	51	2026-08-07 21:57:21	2026-08-07 21:57:21	\N	\N	\N
43	procurement_governance	Procurement Governance	Supply Chain	550.00	t	t	53	2026-08-08 03:00:56	2026-08-08 03:00:56	\N	\N	\N
44	supplier_due_diligence	Supplier Intelligence & Due Diligence	Operations	450.00	t	t	200	2026-08-17 18:30:21	2026-08-17 18:30:21	\N	\N	\N
39	content_marketing	AI Content Marketing & SEO Suite	Operations & Support	995.00	t	t	50	2026-07-16 21:09:40	2026-11-06 21:42:15	1	10	\N
45	document_management	Document Management	Operations	350.00	t	t	53	2026-08-29 10:22:43	2026-08-29 10:22:43	\N	\N	\N
19	accounting	Accounting	Finance	599.00	t	t	18	2026-07-12 17:44:35	2026-07-12 17:44:35	\N	\N	1
46	finance_executive	Finance Executive	Finance	299.00	t	t	60	2026-09-22 10:21:47	2026-09-22 10:21:47	\N	\N	\N
47	esign	E-Sign	Operations	199.00	t	t	90	2026-09-22 10:21:48	2026-09-22 10:21:48	\N	\N	\N
48	ai_campaign_manager	AI Campaign Manager	Operations & Support	450.00	t	t	20	2026-09-22 10:21:48	2026-09-22 10:21:48	\N	\N	\N
49	leave_bureau	Leave Bureau	HR & People	599.00	t	t	70	2026-09-22 10:21:48	2026-09-22 10:21:48	\N	\N	\N
50	manufacturing	Manufacturing & Production	Operations	650.00	t	t	201	2026-10-12 07:56:30	2026-10-12 07:56:30	\N	\N	\N
51	warehouse_management	Warehouse Management & Intelligence	Operations	650.00	t	t	202	2026-10-13 15:48:19	2026-10-13 15:48:19	\N	\N	\N
52	manufacturing_essentials	Manufacturing — Essentials package	Operations	0.00	t	t	203	2026-10-13 15:49:32	2026-10-13 15:49:32	\N	\N	\N
53	manufacturing_professional	Manufacturing — Professional package	Operations	0.00	t	t	204	2026-10-13 15:49:32	2026-10-13 15:49:32	\N	\N	\N
54	manufacturing_enterprise	Manufacturing — Enterprise package	Operations	0.00	t	t	205	2026-10-13 15:49:32	2026-10-13 15:49:32	\N	\N	\N
55	tax_management	Tax Management & Compliance	Finance	650.00	t	t	206	2026-10-14 11:01:52	2026-10-14 11:01:52	\N	\N	\N
56	workflow	Workflow & Automation	Operations & Support	750.00	t	t	207	2026-10-14 19:56:44	2026-10-14 19:56:44	\N	\N	\N
57	ai_studio	Mkhaya AI Studio	Sales & Service	0.00	f	t	999	2026-10-27 15:25:47	2026-10-27 15:25:47	\N	\N	\N
58	accountant_partner	Accountant Partner	Finance	999.00	t	t	75	2026-11-03 23:11:00	2026-11-03 23:11:00	\N	\N	\N
\.


--
-- Name: billing_modules_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.billing_modules_id_seq', 58, true);


--
-- PostgreSQL database dump complete
--


--
-- PostgreSQL database dump
--


-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: plans; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.plans (id, code, name, price_monthly, max_users, modules, is_active, sort, created_at, updated_at, ai_token_quota, sms_quota, regular_price_monthly, storage_gb, support) FROM stdin;
1	starter	Starter	399.00	5	["user_management","roles_permissions","reports","company_settings","system_settings"]	t	1	2026-07-12 17:44:35	2026-07-12 17:44:35	200000	100	599.00	2	{"email":true,"whatsapp":false,"phone":false,"remote_hours":"1 Hour\\/Month","response":"24 Business Hours","emergency":false,"account_manager":false,"training":"Knowledge Base","health_check":null,"priority_bugfix":false,"onsite":null}
4	growth	Growth	699.00	10	["user_management","roles_permissions","reports","company_settings","system_settings"]	t	2	2026-07-12 17:44:35	2026-07-12 17:44:35	500000	500	999.00	5	{"email":true,"whatsapp":true,"phone":true,"remote_hours":"3 Hours\\/Month","response":"8 Business Hours","emergency":false,"account_manager":false,"training":"Online Videos","health_check":"Annual","priority_bugfix":false,"onsite":null}
5	business	Business	1299.00	25	["user_management","roles_permissions","reports","company_settings","system_settings"]	t	3	2026-07-12 17:44:35	2026-07-12 17:44:35	2000000	2000	1999.00	20	{"email":true,"whatsapp":true,"phone":true,"remote_hours":"8 Hours\\/Month","response":"4 Business Hours","emergency":true,"account_manager":true,"training":"2 Remote Sessions\\/Year","health_check":"Quarterly","priority_bugfix":true,"onsite":"1 Visit\\/Year"}
3	enterprise	Enterprise	3999.00	100	\N	t	4	2026-07-12 17:44:35	2026-07-12 17:44:35	10000000	10000	5999.00	100	{"email":true,"whatsapp":true,"phone":true,"remote_hours":"Unlimited (Fair Use)","response":"1 Business Hour","emergency":true,"account_manager":true,"training":"Unlimited Remote Training","health_check":"Monthly","priority_bugfix":true,"onsite":"1 Visit\\/Quarter"}
2	standard	Standard	1499.00	25	["accounting","ai_campaign_manager","asset_management","board_committee","boarding","budget_management","claims","company_settings","conferences","content_management","content_marketing","contract_management","crm","disciplinary","document_management","employee_reputation","esign","finance_executive","fleet_management","help_desk","learning_management","leave_bureau","leave_management","marketplace","offboarding","payroll","payroll_bureau","performance_appraisal","petty_cash","policy_enforcement","popia_compliance","procurement_governance","procurement_requests","project_management","recognition","recruitment","reports","resources","roles_permissions","sheq","staff_loans","supplier_due_diligence","supplier_management","surveys","system_settings","tender_pursuit","time_attendance","user_management","whistleblower"]	f	2	2026-07-12 17:44:35	2026-09-22 10:28:05	1000000	500	\N	\N	\N
\.


--
-- Name: plans_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.plans_id_seq', 5, true);


--
-- PostgreSQL database dump complete
--


--
-- PostgreSQL database dump
--


-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: security_categories; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.security_categories (id, name, icon, description, active, created_at, updated_at) FROM stdin;
4	System Administration	building	System Administration	t	2026-07-12 17:44:43	2026-07-12 17:44:43
\.


--
-- Name: security_categories_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.security_categories_id_seq', 4, true);


--
-- PostgreSQL database dump complete
--


--
-- PostgreSQL database dump
--


-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: security_modules; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.security_modules (id, category_id, uuid, name, path, code_name, active, font_awesome, glyph_icon, created_at, updated_at) FROM stdin;
4	4	1efb8140-17d4-4fdf-8c68-ac0917ac7ae9	User Management	/user-management	user_management	1	users	\N	2026-07-12 17:44:43	2026-07-12 17:44:43
5	4	51831b68-3fcf-4269-8ba8-026ee2a13721	System Settings	/system-settings	system_settings	1	cog	\N	2026-07-12 17:44:43	2026-07-12 17:44:43
6	4	083e8713-bfc2-4473-95e7-6fa4df216403	Company Settings	/company-settings	company_settings	1	building	\N	2026-07-12 17:44:43	2026-07-12 17:44:43
7	4	2c96db62-5691-4e21-ac68-5c8f42ad229c	Roles & Permissions	/roles-permissions	roles_permissions	1	user-shield	\N	2026-07-12 17:44:43	2026-07-12 17:44:43
8	4	eb32575f-57f7-4fcb-bf22-4a4ec4e94c38	Reports	/reports	reports	1	chart-bar	\N	2026-07-12 17:44:43	2026-07-12 17:44:43
\.


--
-- Name: security_modules_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.security_modules_id_seq', 8, true);


--
-- PostgreSQL database dump complete
--


--
-- PostgreSQL database dump
--


-- Dumped from database version 18.4 (Homebrew)
-- Dumped by pg_dump version 18.4 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: security_module_ribbons; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.security_module_ribbons (id, uuid, module_id, sort_order, access_level, active, ribbon_name, ribbon_path, description, font_awesome, created_at, updated_at) FROM stdin;
47	c499f07d-85c3-4e42-9239-8159f53babaa	4	1	5	1	Manage Users	settings/manage-users	Manage Users	users	2026-07-12 17:44:43	2026-11-05 23:39:48
758	bd8e1489-8a49-4f37-afc5-21395c49789e	4	2	5	1	Access	settings/user-access-hub	Access	key	2026-08-06 18:47:05	2026-11-05 23:39:48
50	c70114b0-22f9-453f-be43-013495da5259	5	2	5	1	Company Identity	settings/setup	Company Identity	cog	2026-07-12 17:44:43	2026-11-05 23:39:48
740	21493c4c-af13-44de-8f8b-83a45260c038	5	3	5	1	Platform	settings/platform-hub	Platform	server	2026-08-06 18:47:05	2026-11-05 23:39:48
61	50331e5f-f9fe-4226-8768-03f6ae334334	8	1	5	1	System Usage	settings/manage-users/report	System Usage	chart-line	2026-07-12 17:44:43	2026-11-05 23:39:48
757	c5435443-b420-44b0-9c5f-3b0c2b170d6e	8	2	5	1	Governance	settings/reports-governance-hub	Governance	shield	2026-08-06 18:47:05	2026-11-05 23:39:48
759	9a23074b-37c3-448f-9318-548eed54d4fb	6	1	5	1	Structure	settings/structure-hub	Structure	id-badge	2026-08-06 18:47:05	2026-11-05 23:39:48
58	a7feb7c4-0b64-4749-9fdc-2b0fa56d244b	7	1	5	1	Roles	settings/roles	Roles	fa-user-tag	2026-07-12 17:44:43	2026-11-05 23:39:48
761	25ed8c13-cb34-47da-8678-5de3a6191301	7	2	5	1	Access Control	settings/access-control-hub	Access Control	lock	2026-08-06 18:47:05	2026-11-05 23:39:48
\.


--
-- Name: security_module_ribbons_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.security_module_ribbons_id_seq', 761, true);


--
-- PostgreSQL database dump complete
--


