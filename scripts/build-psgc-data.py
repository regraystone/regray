#!/usr/bin/env python3
"""
Build the Philippine address reference data the seeders read
(database/seeders/data/psgc/*.csv) from two published sources:

- The PSGC (Philippine Standard Geographic Code) of the Philippine Statistics
  Authority, as the PSGC API (https://psgc.gitlab.io/api/) publishes it:
  regions, provinces, cities and municipalities, and barangays.
- The PHLPost ZIP codes, as GeoNames publishes them
  (https://download.geonames.org/export/zip/PH.zip, CC BY 4.0, credit
  GeoNames). Each ZIP code is matched to the PSGC city or municipality it
  serves, by code where GeoNames gives one, else by name within its province.

Usage, with the downloads in one folder (regions.json, provinces.json,
cities-municipalities.json, barangays.json, PH.txt):

    python3 -I scripts/build-psgc-data.py <download folder>

The CSVs are rewritten in place; commit them, and note the date in the README.
"""

import csv
import json
import re
import sys
import unicodedata
from pathlib import Path

SOURCE = Path(sys.argv[1])
OUT = Path(__file__).resolve().parent.parent / 'database' / 'seeders' / 'data' / 'psgc'
NCR = '130000000'


def load(name):
    with open(SOURCE / name, encoding='utf-8') as handle:
        return json.load(handle)


def normalize(name):
    """The same normalization as App\\Support\\PhPlaceName and ph-address.ts."""
    text = unicodedata.normalize('NFKD', name)
    text = ''.join(c for c in text if not unicodedata.combining(c)).lower()
    text = re.sub(r'\(.*?\)', ' ', text)
    text = re.sub(r'\b(city of|province of|municipality of|barangay|brgy\.?|pob\.?|poblacion)\b', ' ', text)
    text = re.sub(r'\bcity\b', ' ', text)
    text = re.sub(r'\bsta\.?\b', 'santa', text)
    text = re.sub(r'\bsto\.?\b', 'santo', text)
    text = re.sub(r'[^a-z0-9]+', ' ', text)

    return ' '.join(text.split())


def write(name, header, rows):
    OUT.mkdir(parents=True, exist_ok=True)

    with open(OUT / name, 'w', encoding='utf-8', newline='') as handle:
        out = csv.writer(handle, lineterminator='\n')
        out.writerow(header)
        out.writerows(rows)


regions = sorted(load('regions.json'), key=lambda r: r['code'])
provinces = sorted(load('provinces.json'), key=lambda p: p['code'])
cities = sorted(load('cities-municipalities.json'), key=lambda c: c['code'])
barangays = sorted(load('barangays.json'), key=lambda b: b['code'])

write('regions.csv', ['code', 'name', 'designation'], [
    [r['code'], r['regionName'] if r['code'] == NCR else r['name'], r['regionName']] for r in regions
])
write('provinces.csv', ['code', 'name', 'region_code'], [
    [p['code'], p['name'], p['regionCode']] for p in provinces
])
write('cities.csv', ['code', 'name', 'region_code', 'province_code', 'is_city'], [
    [c['code'], c['name'], c['regionCode'], c['provinceCode'] or '', 1 if c['isCity'] else 0] for c in cities
])
write('barangays.csv', ['code', 'name', 'city_code'], [
    [b['code'], b['name'], b['cityCode'] or b['municipalityCode']] for b in barangays
])

city_codes = {c['code'] for c in cities}
city_scope = {c['code']: c['provinceCode'] or c['regionCode'] for c in cities}
province_by_name = {normalize(p['name']): p['code'] for p in provinces}
cities_by_province = {}
barangays_by_province = {}

for c in cities:
    cities_by_province.setdefault(city_scope[c['code']], {}).setdefault(normalize(c['name']), c['code'])

# A ZIP code named after a district ("Matina", "Balibago") is the city that barangay is in.
# A name two towns of the province share says nothing, so it is left out.
for b in barangays:
    city = b['cityCode'] or b['municipalityCode']
    names = barangays_by_province.setdefault(city_scope[city], {})
    key = normalize(b['name'])
    names[key] = city if names.get(key, city) == city else None

# GeoNames spellings that differ from the PSGC's, and provinces it names by an older map.
SPELLINGS = {
    'santotomas': 'santo tomas', 'naguillan': 'naguilian', 'gen tinio': 'general tinio',
    'munoz': 'science of munoz', 'carrangalan': 'carranglan', 'gen m natividad': 'general mamerto natividad',
    'san jose de buanavista': 'san jose de buenavista', 'san remegio': 'san remigio', 'jetafe': 'getafe',
    'san policarpio': 'san policarpo', 'wright': 'paranas', 'president manuel a roxas': 'president manuel a roxas',
    'ozamis': 'ozamiz', 'gov generoso': 'governor generoso', 'enrique magalona': 'e b magalona',
}
PROVINCE_ALIASES = {'maguindanao': ['maguindanao del norte', 'maguindanao del sur']}


def candidate_scopes(region, province):
    if region == 'Metro Manila':
        return [NCR]

    name = normalize(province)
    names = [name, *PROVINCE_ALIASES.get(name, [])]

    return [province_by_name[n] for n in names if n in province_by_name]


def find_city(scopes, *names):
    for name in names:
        key = normalize(name)
        key = re.sub(r'^(gen|pres|pre)\b', lambda m: 'general' if m.group(1) == 'gen' else 'president', key)
        key = SPELLINGS.get(key, key)

        if key == '':
            continue

        for scope in scopes:
            for table in (cities_by_province, barangays_by_province):
                found = table.get(scope, {}).get(key)  # None when shared or absent

                if found is not None:
                    return found

    return None


pairs = set()
unmatched = []

with open(SOURCE / 'PH.txt', encoding='utf-8') as handle:
    for line in handle:
        cols = line.rstrip('\n').split('\t')
        postal, place, region, province, province_code, city, city_code = cols[1], cols[2], cols[3], cols[5], cols[6], cols[7], cols[8]
        match = None

        if city_code in city_codes:
            match = city_code
        elif province_code in city_codes:
            match = province_code  # The City of Manila is filed as a district.
        else:
            # "Dau, Mabalacat" names a place in a town: try the town after the comma too.
            parts = [p.strip() for p in place.split(',')]
            match = find_city(candidate_scopes(region, province), city, place, *reversed(parts))

        if match is None:
            unmatched.append(line.strip())
        else:
            pairs.add((postal, match))

write('postal_codes.csv', ['postal_code', 'city_code'], sorted(pairs))

print(f'{len(regions)} regions, {len(provinces)} provinces, {len(cities)} cities, {len(barangays)} barangays')
print(f'{len(pairs)} postal codes matched, {len(unmatched)} GeoNames rows left unmatched')
print(f'{len(city_codes - {city for _, city in pairs})} cities have no postal code')
