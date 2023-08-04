# Migration installation jobs from legacy system (NOS, Account API) to DMS Home installations

## Legacy system data

The legacy system contains installations data in `clb_leaseco_transaction` table. This table contains data about installation like installer name, costs, installation progress, etc. 

| **Column**           | **Description**                                                                                                  |
|----------------------|------------------------------------------------------------------------------------------------------------------|
| id                   | Installation job id                                                                                              |
| user_id              | User id                                                                                                          |
| leaseco_org_name     | Name of a company that provides home charger                                                                     |
| connection_name      | Name of connection that provides home charger                                                                    |
| employer_id          | Employer id                                                                                                      |
| driver_name          | Driver first and last name                                                                                       |
| transaction_type     | `3` as only existing value. Probably meant to identify a kind of leaseco transaction                             |
| installation_status  | DEPRECATED. The `job_status` is used intead                                                                      |
| installer_id         | ID of charger installer that comes from Installer Portal (BizApps/NetSuite) after job is created there           |
| installer_name       | Name of charger installer. Comes together with `installer_id`                                                    |
| install_location     | Address where charger has to be installed                                                                        |
| installation_company | Not used                                                                                                         |
| home_serial_num      | MAC address of charger (without colons)                                                                          |
| reimbursement_id     | Not used                                                                                                         |
| currency_iso_code    | Driver currency as ISO 4217                                                                                      |
| amount               | Comes from Installer Portal                                                                                      |
| subtotal_amount      | Comes from Installer Portal                                                                                      |
| var_rate             | 0-100. Comes from Installer Portal                                                                               |
| vat_amount           | Comes from Installer Portal                                                                                      |
| actual_amount        | Comes from Installer Portal                                                                                      |
| approved_amount      | Comes from Installer Portal                                                                                      |
| job_status           | Status of installation (integer). See `JobStatus` in the `dal` repository for more details                       |
| external_id          | Installation ID on the installer side. comes from Installer Portal (BizApps/NetSuite) after job is created there |
| job_document         | Link to installation job document signed by the driver                                                           |
| connection_id        | ID of connection that provides home charger                                                                      |
| completion_date      | Date and time when job is marked as completed                                                                    |
| driver_group_id      | Connection group ID                                                                                              |
| activation_date      | Date and time of charger activation attempt (set at the beginning of charger activation)                         |

Also, when driver requests home charger the contact information is getting stored in `clb_business_details` table for the connection that provides home charger.

Here are contact information stored in `clb_business_details` table:

| **Column**                  | **Description**                                                                                      |
|-----------------------------|------------------------------------------------------------------------------------------------------|
| address1                    | Address line 1                                                                                       |
| address2                    | Address line 2                                                                                       |
| city                        | City name. Free text that driver provides while requesting home charger                              |
| state                       | State name. State name driver selects from the drowdown list while requesting home charger           |
| state_code                  | State code. Filled when provided together with state name in NOS db                                  |
| country                     | Country name. Country name driver selects from the drowdown list while requesting home charger       |
| country_id                  | Country ID in NOS db                                                                                 |
| country_code                | Country code in NOS db                                                                               |
| zipcode                     | Zipcode. Provided as free text by driver while requesting home charger                               |
| contact_number              | Contact number. Provided as free text by driver while requesting home charger                        |
| contact_number_dialing_code | Contact number dialing code. Selected by driver from the dropdown list while requesting home charger |

## DMS Home installations data

DMS home installations splits an installation job into **HomeChargerRequest** and **InstallerJob**. 

### HomeChargerRequest

**HomeChargerRequest** is stored in DMS Home installations DB in `dms_home_charger_requests` table. One HomeChargerRequest contains information about driver request for home charger. It is created when driver requests home charger. Home charger has `request_status` column that identifies if request is approved or not. The approval state comes from connection status, meaning once connection gets approved the request gets approved as well. Request can be created having `PENDING` or `APPROVED` status (when connection is auto-approved). Driver has one home charger request per connection. For example, if driver had requested home charger the request got rejected and driver requests home charger again no new request is created. Instead the existing request is updated with new data. 

For request tracking purposes the "image" of request is copied in the `dms_home_charger_requests_history` table using MySql triggers on insert and update.

| **Column**          | **Description**                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
|---------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| driver_id           | Driver id                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| company_id          | Company id that provides home charger                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| connection_id       | Connection id that provides home charger                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| driver_group_id     | Connection group id                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| leaseco_org_id      | Company Org ID (`clb_company.organization_id`)                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| leaseco_org_name    | Company name (`clb_company.name`)                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| connection_name     | Connection name                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| driver_first_name   | Driver first name                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| driver_last_name    | Driver last name                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| request_status      | Request status that currently comes from connection status. See [RequestStatus](https://github.com/ChargePoint/DMS-Home-Installations/blob/256a4329d31881ec2363762a6f28c89f17aed8ab/dms/src/main/java/com/chargepoint/dms/dao/enums/RequestStatus.java#L1) and [RequestStatusMapper](https://github.com/ChargePoint/DMS-Home-Installations/blob/256a4329d31881ec2363762a6f28c89f17aed8ab/dms/src/main/java/com/chargepoint/dms/mappers/RequestStatusMapper.java#L1) for more details |
| charger_deactivated | Bool. Similar to installer job status but as there are multiple reasons why charger got deactivated we decided to have separate column for this on the request level (this might be changed later)                                                                                                                                                                                                                                                                                   |
| email               | Driver email                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| phone_number        | Driver phone number                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| dialing_code        | Driver phone number dialing code                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| address1            | Driver address line 1                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| address2            | Driver address line 2                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| zip_code            | Driver zip code                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| city                | Driver city. Free text                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| country_id          | Driver country id from NOS db                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| country_code        | Driver country code from NOS db                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| country_name        | Driver country name                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| state_id            | Driver state id from NOS db                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| state_code          | Driver state code from NOS db                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| state_name          | Driver state name                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| driver_verf_values  | Connection verification values. JSON string `[{"key":"field name","value":"field value"},...]`                                                                                                                                                                                                                                                                                                                                                                                       |
| created_at          | Request creation date and time                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| updated_at          | Request update date and time                                                                                                                                                                                                                                                                                                                                                                                                                                                         |

The `dms_home_charger_requests_history` has the same columns as `dms_home_charger_requests` table plus `request_id` column that is the primary key of `dms_home_charger_requests` table.

### InstallerJob

**InstallerJob** contains only installation specific information that Installer Portal shares/sync with DMS Home installations.

For installer job tracking purposes the "image" of installer job is copied in the `dms_installer_jobs_history` table using MySql triggers on insert and update.

| **Column**        | **Description**                                                                                                                                                                                                              |
|-------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| id                | Installer job id                                                                                                                                                                                                             |
| request_id        | Home charger request id                                                                                                                                                                                                      |
| external_id       | Installation ID on the installer side. comes from Installer Portal (BizApps/NetSuite) after job is created there                                                                                                             |
| employer_id       | Employer ID                                                                                                                                                                                                                  |
| installer_id      | Installer ID                                                                                                                                                                                                                 |
| installer_name    | Installer name                                                                                                                                                                                                               |
| currency_iso_code | Driver currency ISO code                                                                                                                                                                                                     |
| amount            | Comes from Installer Portal                                                                                                                                                                                                  |
| subtotal_amount   | Comes from Installer Portal                                                                                                                                                                                                  |
| vat_rate          | Comes from Installer Portal                                                                                                                                                                                                  |
| vat_amount        | Comes from Installer Portal                                                                                                                                                                                                  |
| actual_amount     | Comes from Installer Portal                                                                                                                                                                                                  |
| approved_amount   | Comes from Installer Portal                                                                                                                                                                                                  |
| created_at        | Job creation date and time                                                                                                                                                                                                   |
| updated_at        | Last job update date and time                                                                                                                                                                                                |
| installation_date | **TODO** Add description                                                                                                                                                                                                     |
| activation_date   | Date and time of charger activation attempt (set at the beginning of charger activation)                                                                                                                                     |
| completion_date   | Date and time when job is marked as completed                                                                                                                                                                                |
| job_status        | Installation status. See [JobStatus](https://github.com/ChargePoint/DMS-Home-Installations/blob/256a4329d31881ec2363762a6f28c89f17aed8ab/dms/src/main/java/com/chargepoint/dms/dao/enums/JobStatus.java#L1) for more details |
| job_document      | Link to installation job document signed by the driver                                                                                                                                                                       |
| synced_to_nos     | Flag that identifies if job is created in legacy system. Needed during transition period as job is synced with legacy system to not break reports                                                                            |

The `dms_installer_jobs_history` has the same columns as `dms_installer_jobs` table plus `job_id` column that is the primary key of `dms_installer_jobs` table.

## Migration process

### HomeChargerRequest mapping

We want to have maximum available info in NOS being migrated to DMS Home Installations. For **HomeChargerRequest** we can recreate/migrate requests together with theirs historical changes based on `clb_business_details` table and `clb_company_driver_affiliation` table. If `clb_company_driver_affiliation` has connection details business connection and `has_home_requested: 1` then a request is going to be created for the driver connection.

Creating **HomeChargerRequest** with historical changes steps:
1. Get all the drivers from `clb_business_details`
2. If driver has `clb_company_driver_affiliation` with `has_home_requested: 1` then create **HomeChargerRequest** with `REQUESTED` status and `created_at` set to `clb_business_details.create_date`
3. If `clb_business_details.connection_approval_date` is not empty then update **HomeChargerRequest** with `APPROVED` status and `updated_at` set to `clb_business_details.connection_approval_date`
4. If `clb_business_details.connection_discontinue_date` is not empty: 
    
    **a**. if `clb_business_details.connection_approval_date` is empty then update **HomeChargerRequest** with `DENIED` status and `updated_at` set to `clb_business_details.connection_discontinue_date`
    
    **b**. if `clb_business_details.connection_approval_date` is not empty then update **HomeChargerRequest** with `DELETED` status and `updated_at` set to `clb_business_details.connection_discontinue_date` (the connection has been approved but then got removed)

5. If driver has another row in `clb_business_details` for the same connection then repeat steps 3 and 4 for the existing request

> NOTE: The insert and update queries triggers creation the "image" of home charger request in `dms_home_charger_requests_history` table

| **Coulomb DB source**                                                                                   | **HomeChargerRequest field** |
|---------------------------------------------------------------------------------------------------------|------------------------------|
|                                                                                                         | request_id                   |
| clb_business_details.subscriber_id                                                                      | driver_id                    |
| clb_company_driver_affiliation.company_id                                                               | company_id                   |
| clb_business_details.connection_id                                                                      | connection_id                |
| clb_business_details.driver_group_id                                                                    | driver_group_id              |
| clb_company.organization_id                                                                             | leaseco_org_id               |
| clb_company.name                                                                                        | leaseco_org_name             |
| clb_company.organization_id                                                                             | leaseco_org_id               |
| clb_company.currency_iso_code <br/> **or** clb_instance_config.instance_currency                        | currency_iso_code            |
| clb_business_details.driver_name (split by space)                                                       | driver_first_name            |
| clb_business_details.driver_name (split by space)                                                       | driver_last_name             |
| See above                                                                                               | request_status               |
| 0                                                                                                       | charger_deactivated          |
| clb_user_login.email                                                                                    | email                        |
| clb_business_details.contact_number                                                                     | phone_number                 |
| clb_business_details.contact_number_dialing_code                                                        | dialing_code                 |
| clb_business_details.address1                                                                           | address1                     |
| clb_business_details.address2                                                                           | address2                     |
| clb_business_details.zipcode                                                                            | zip_code                     |
| clb_business_details.country_id                                                                         | country_id                   |
| clb_business_details.country                                                                            | country_name                 |
| clb_business_details.country_code                                                                       | country_code                 |
| clb_business_details.state                                                                              | state_name                   |
| clb_business_details.state_code                                                                         | state_code                   |
| clb_business_details.country_id                                                                         | country_id                   |
| clb_states.id                                                                                           | state_id                     |
| clb_company_connection_fields and clb_driver_connection_values                                          | driver_verf_values           |
| clb_business_details.create_date (of the oldest entry if driver has multiple)                           | created_at                   |
| clb_business_details.connection_approval_date <br/> or clb_business_details.connection_distontinue_date | updated_at                   |

### InstallerJob mapping

TBD

| **Coulomb DB source**                                               | **InstallerJob field** |
|---------------------------------------------------------------------|------------------------|
| Can be found by `clb_leaseco_transaction` user ID and connection ID | request_id             |
| clb_leaseco_transaction.employer_id                                 | employer_id            |
| clb_leaseco_transaction.installer_id                                | installer_id           |
| clb_leaseco_transaction.installer_name                              | installer_name         |
| clb_leaseco_transaction.home_serial_num                             | mac_address            |
| Same as in the linked HomeChargerRequest                            | currency_iso_code      |
| clb_leaseco_transaction.amount                                      | amount                 |
| clb_leaseco_transaction.subtotal_amount                             | subtotal_amount        |
| clb_leaseco_transaction.vat_rate                                    | vat_rate               |
| clb_leaseco_transaction.vat_amount                                  | vat_amount             |
| clb_leaseco_transaction.actual_amount                               | actual_amount          |
| clb_leaseco_transaction.approved_amount                             | approved_amount        |
| clb_leaseco_transaction.create_date                                 | created_at             |
|                                                                     | updated_at             |
| NULL                                                                | installation_date      |
| clb_leaseco_transaction.activation_date                             | activation_date        |
| clb_leaseco_transaction.completion_date                             | completion_date        |
| clb_leaseco_transaction.job_status                                  | job_status             |
| clb_leaseco_transaction.job_document                                | job_document           |
| clb_leaseco_transaction.external_id                                 | external_id            |
| `1`                                                                 | synced_to_nos          |


## To discuss

* Some drivers have multiple installation jobs (example: 877615,877935,20476375,21209415,21210865). Probably just had to be "merged" in one job 
* The next driver don't have records in `clb_company_driver_affiliation` table but have installation jobs: 
  `20475125,21626635,21982205,21973175,30904895,31580645,32881185,31305235,39536565,39403025`, two of them 
  `31580645` and `31305235` are marked as `ACTIVE` in `clb_business_details` the rest are marked as `DELETED`.
  The drivers `32881185,39403025,39403025,39536565` disconnected in 2023 (the last two in July). 
* The next drivers have installation jobs but clb_company_driver_affiliation doesn't say they have home charger requested:
  `31490765,31490765,31490765`. The job status is 11 (`CLOSED_NOT_INSTALLED_OTHER`) and connection is deleted
   **TODO:** Ask BizApps if they want us to send them connection discontinue status so they can mark the job as `CLOSED_NOT_INSTALLED_OTHER`
