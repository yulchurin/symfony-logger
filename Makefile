DOCKER_COMPOSE = docker compose -f docker-compose.yml

restart: down up

build:
	$(DOCKER_COMPOSE) build --progress=plain
up:
	$(DOCKER_COMPOSE) up -d
down:
	$(DOCKER_COMPOSE) down
cli:
	$(DOCKER_COMPOSE) exec app bash
bup:
	$(DOCKER_COMPOSE) up -d --build
build-app:
	$(DOCKER_COMPOSE) build app --progress=plain
build-renew:
	$(DOCKER_COMPOSE) build app --no-cache --progress=plain
