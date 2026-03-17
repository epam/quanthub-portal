# Introduction
Quanthub Portal Drupal Profile

## Typical Demo istallation
```
drush si quanthub
drush en default_content
drush en quanthub_elasticsearch
drush en quanthub_tvi
drush en quanthub_auth
drush en quanthub_sdmx
drush en quanthub_sdmx_proxy #(optional)
drush en quanthub_datasetexplorer #(optional, needs library)
```

## Routes

### Profile rotes

- `/admin/config/system/settings` - Settings (admin)

### Quanthub SDMX Proxy

- `/sdmx/*` - V1 SDMX API forwarder
- `/sdmx-download/*` - V2 SDMX API forwarder

### Quanthub Dataset Explorer

- `/explorer` - Dataset Explorer page

## UUIDs

### Profile UUIDs

- Menus
  - `867e796f-d11c-4a8d-bfb2-141893f10c82`: "Data" item of the Main menu
- Nodes
  - `7d8f9595-fd43-4571-b1a4-a627905ddf5f`: "Privacy Policy" page
  - `965ffd8e-ee26-4f8f-a794-4250e5135b0c`: "Terms of Use" page

### Quanthub TVI UUIDs

- Taxonomy terms
  - `e70f41d1-4eb4-4c45-a348-9deb5c4d2c73`: Homepage
  - `c943ad82-1fb8-40c6-9a71-3651b6d3168b`: Dashboards listing
  - `93c52c6f-c92f-44f3-82f3-b8f2553adb89`: Datasets listing
  - `73d98cb9-5a59-43ac-b01f-a7024c2b28e6`: News listing
  - `02553986-d605-42ab-acd6-85bea2cd1780`: Publications listing
  - `41688334-b51c-4cc0-a171-63fc9c38bb1b`: Releases listing

### Quathub Dataset Explorer UUIDs

- Menus
  - `ce2d18f6-536e-46bd-b374-9f26455039d4`: "Explore data" item of the Main menu

## Environment overrides

### Quanthub Elacticsearch

- `ELASTIC_URL`: (e.g 'http://example.com:9200')
- `ELASTIC_PREFIX`: Empty by default
- `ELASTIC_USER`: Empty by default
- `ELASTIC_PASSWORD`: Empty by default

### Quanthub Authentication

- `ANONYMOUS_TOKEN`: Static token for anonymous user
- `ANONYMOUS_TOKEN_ENDPOINT_URL`: Anonymous token endpoint URL
- `OIDC_CONFIG_ENDPOINT_URL`: OAuth configuration URL (oidc module)
- `OIDC_CLIENT_ID`: OAuth client ID (oidc module)
- `OIDC_SCOPES_JSON`: JSON array of OAuth scopes (oidc module)
- `OIDC_AUDIENCE`: Custom endpoints audience/client ID (oidc module)
- `OIDC_TOKEN_ENDPOINT_URL`: Custom user token endpoint URL (oidc module)
- `OIDC_REFRESH_ENDPOINT_URL`: Custom token refresh endpoint URL (oidc module)

### Quanthub SDMX

- `SDMX_API_URL`: Base URL of SDMX API endpoint
- `SDMX_WORKSPACE_ID`: SDMX Workspace ID

### Quanthub Dataset Explorer

- `SDMX_FACTOR_ATTRIBUTE_ID`: SDMX Factor attribute ID (extra column in grid)
- `SDMX_UOM_ATTRIBUTE_ID`: SDMX Units of Measure attribute ID (extra column in grid)
