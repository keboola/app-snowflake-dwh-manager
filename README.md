# Snowflake Data Warehouse Manager

Used to create business schema, that will be written to by Snowflake writer and that will be read-only shared to a group of analytics. 

## Typical workflow

* you create a schema `accounting` and fill in the data from you accounting software via Keboola Connection's Snowflake Writer
* you create a schema `sales` and fill in the data from you CRM software via Keboola Connection's Snowflake Writer
* you create a schema `development` and fill in the data from you CRM software via Keboola Connection's Snowflake Writer
* you create a user for you analytic `maria@example.com`, that has read-only access to both and in addition has write access to `development`
* Maria gets read access to `accounting` and `sales` and read-write access to `development` as well as her own `maria_example_com` schema (with write access), so that she can combine the data from accounting and sales to deliver an analysis. The data is safe, because she only has read only access to the original data, but in the same time she can read and write to the development schema to collaborate with the development team. She's free to use her own schema when she wants to do some rough prototyping.

# Usage

There are two types of configs - schema config and user config. App detects automatically which is which. 

## Schema config
```json
{
    "parameters" : {
        "master_host": "some.snowflakecomputing.com",
        "master_user": "username_that_can_create_roles_and_schemas",
        "#master_password": "password",
        "master_database": "database_that_will_hold_the_schemas",
        "warehouse": "snowflake_warehouse",
        "business_schema": {
            "schema_name": "accounting",
            "statement_timeout": 10800
        }
    }
}
```

`schema_name`: the name of the schema to be created, keep it short and simple

## User config
```json
{
    "parameters" : {
        "master_host": "some.snowflakecomputing.com",
        "master_user": "username_that_can_create_roles_and_schemas",
        "#master_password": "password",
        "master_database": "database_that_will_hold_the_schemas",
        "warehouse": "snowflake_warehouse",
        "user": {
           "email": "maria@example.com",
           "business_schemas" : ["accounting", "sales"],
           "schemas" : [
                {"name": "development","permission": "read"}
           ],
           "statement_timeout": 10800,
           "disabled": false
       }
    }
}
```

`email`: email address that will be used to create a login for the Snowflake user
`business_schemas`: array of schema names to be assigned to the new user as read-only
`schemas`: array of objects containing schema name to be assigned to the new user and permission to assign (read|write)
`disabled`: user is not be able to log in while their account is disabled
`statement_timeout`: Amount of time, in seconds, after which a running SQL statement is canceled.
    - For *schema config* it applies to schema's default RW user.
    - For *user config* it applies to the user himself.

## Changing access configs

App can be run multiple times with the same config and the result is always the same. It will do it's best to put the resources in expected state. That means:

* it WILL create missing user schemas or business schemas 
* it WILL create missing roles if they were deleted by mistake
* it WILL recreate users
* it WILL grant privileges to new bussiness schemas to users
* it WILL revoke business schema privileges from users
* it WILL change users "disabled" flag accordingly
* it WILL NOT drop business schemas  
* it WILL NOT drop users
* it WILL NOT drop roles

## Master user

Master users needs to be able to create users, schemas and roles.  

```
CREATE DATABASE "DWHM_MYDATAWAREHOUSE" DATA_RETENTION_TIME_IN_DAYS = 7;

CREATE ROLE "DWHM_MYDATAWAREHOUSE";

GRANT OWNERSHIP ON DATABASE "DWHM_MYDATAWAREHOUSE" TO ROLE "DWHM_MYDATAWAREHOUSE";

CREATE USER "DWHM_MYDATAWAREHOUSE"
PASSWORD = "STRONG PASSWORD"
DEFAULT_ROLE = "DWHM_MYDATAWAREHOUSE"
TYPE = LEGACY_SERVICE;

GRANT ROLE "DWHM_MYDATAWAREHOUSE" TO USER "DWHM_MYDATAWAREHOUSE";

GRANT CREATE ROLE ON ACCOUNT TO ROLE "DWHM_MYDATAWAREHOUSE" WITH GRANT OPTION;
GRANT CREATE USER ON ACCOUNT TO ROLE "DWHM_MYDATAWAREHOUSE" WITH GRANT OPTION;
GRANT USAGE ON WAREHOUSE "KEBOOLA_PROD" TO ROLE "DWHM_MYDATAWAREHOUSE" WITH GRANT OPTION;
```

Please make sure that all master users have the `DWHM_` prefix so they can be easily filtered out.  
### Cleanup

```
GRANT ROLE "DWHM_MYDATAWAREHOUSE" TO ROLE "ACCOUNTADMIN";
USE ROLE "DWHM_MYDATAWAREHOUSE";

SHOW ROLES LIKE 'DWHM_MYDATAWAREHOUSE_%';
DROP ROLE "DWHM_MYDATAWAREHOUSE_...";

USE ROLE "ACCOUNTADMIN"; /* without using ACCOUNTADMIN, USERS can't be SELECTed */
SHOW USERS LIKE 'DWHM_MYDATAWAREHOUSE_%';
DROP USER "DWHM_MYDATAWAREHOUSE_...";

DROP DATABASE "DWHM_MYDATAWAREHOUSE";

USE ROLE "ACCOUNTADMIN";
DROP USER "DWHM_MYDATAWAREHOUSE";
DROP ROLE "DWHM_MYDATAWAREHOUSE";
```

## Development
 
Clone this repository and init the workspace with following command:

```
git clone https://github.com/keboola/app-snowflake-dwh-manager
cd app-snowflake-dwh-manager
docker-compose build
docker-compose run --rm dev composer install --no-scripts
```

### Tests

Functional tests need `.env` file with Snowflake server credentials. You can create this file from `.env.dist`. By default scenario tests present the component output in console. On Travis, this behavior is suppressed using environment variable `CI=true` to prevent leaking test user credentials into the build log. 

Public key used in tests for user creation has to be generated and placed in `SNOWFLAKE_SCHEMA_PRIVATE_KEY` environment.
Take a look how to generate keys: https://docs.snowflake.com/en/user-guide/key-pair-auth#generate-the-private-key

Run the test suite using this command:

```
docker-compose run --rm dev composer ci
```
 
# Integration

For information about deployment and integration with KBC, please refer to the [deployment section of developers documentation](https://developers.keboola.com/extend/component/deployment/) 
