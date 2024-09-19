[Signifyd Extension for Magento 2](../README.md) > Logs protection

## Personal data protection

By default, the Signifyd extension protects all personal data. The list of protected fields includes:`streetAddress,unit,postalCode,city,provinceCode,countryCode,email,phone,name`.

### Data Protection Rules

* Fields with 12 or more characters will be protected as `3 characters + *** + 3 characters`.e.g. `6146 Honey Bluff Parkway => 614***way`
* Fields with between 7 and 11 characters will be protected as `*** + 3 characters`. e.g., `John Doe => ***Doe`.
* Fields with fewer than 7 characters will be protected as `***`. e.g. `Texas => ***`

### Add custom data protection

It is possible to customize the fields to protect. The list must include the fields mentioned above, separated by commas.

For example, if you want to protect just the name and email, run the command below on your database:

```
`INSERT INTO core_config_data (path, value) VALUES ('signifyd/advanced/private_log_data', 'email,name');`
```
### Update data protection

To modify an existing data protection list, insert the list of new fields you want to protect and then run the following command on your database:

```
UPDATE core_config_data SET value='INSERT-LIST-OF-FIELDS-HERE' WHERE path='signifyd/advanced/private_log_data';
```
### Delete custom data protection

To remove a custom data protection, just delete it from the database:

```
DELETE FROM core_config_data WHERE path='signifyd/advanced/private_log_data';
```

### Check custom data protection

To check the custom data protection, run the command below on your database:

```
SELECT * FROM core_config_data WHERE path = 'signifyd/advanced/private_log_data';
