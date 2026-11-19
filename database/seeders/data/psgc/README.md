# Philippine address reference data

The places an address is picked from (Profile → Addresses, and the address
picker on the Inquire page), read by `PhAddressSeeder`.

| File               | Rows   | What                                                                                                                                                                       |
| ------------------ | ------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `regions.csv`      | 17     | Regions, with their official designation ("Region IV-A", "NCR").                                                                                                           |
| `provinces.csv`    | 81     | Provinces, each under its region.                                                                                                                                          |
| `cities.csv`       | 1,634  | Cities and municipalities. `province_code` is empty for the 17 of the National Capital Region and the two independent cities in no province (Isabela City, Cotabato City). |
| `barangays.csv`    | 42,046 | Barangays, each under its city or municipality.                                                                                                                            |
| `postal_codes.csv` | 1,819  | PHLPost ZIP codes and the city or municipality each serves. 60 cities and municipalities have none listed; their postal code is optional.                                  |

## Sources

- **PSGC** (Philippine Standard Geographic Code), Philippine Statistics
  Authority, as published by the PSGC API: <https://psgc.gitlab.io/api/>
  (`regions`, `provinces`, `cities-municipalities`, `barangays`).
- **Postal codes**: GeoNames, <https://download.geonames.org/export/zip/PH.zip>,
  licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).
  Postal code data © GeoNames (<https://www.geonames.org>), matched to PSGC
  cities by `scripts/build-psgc-data.py`.

Downloaded 2026-11-18.

## Refreshing

Download the four PSGC lists as JSON and `PH.txt` from the GeoNames zip into
one folder, then:

```sh
python3 -I scripts/build-psgc-data.py <download folder>
```

Commit the rewritten CSVs, update the counts and date above, and re-run
`php artisan db:seed --class=PhAddressSeeder`, which updates the rows in place.
