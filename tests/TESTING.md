# Testing Instructions for package development

### Running the dynamodb locally

Our dynamolocal runs in a docker container. Run `docker-compose up -d` to setup the container. The container enables you to run the dynamodb locally for development and also for tests. If you already have an instance of dynamodb local running in your machine or in a container, it's not necessary to setup the containers.

### Setting endpoint

The testing client is set in http://localhost:8000, so set this as your 'DYNAMODB_LOCAL_ENDPOINT' to this address. Also, set 'DYNAMODB_LOCAL' to true.

