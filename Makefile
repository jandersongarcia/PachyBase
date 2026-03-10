up:
	docker compose -f docker/docker-compose.yml up -d

down:
	docker compose -f docker/docker-compose.yml down

logs:
	docker compose -f docker/docker-compose.yml logs -f

restart:
	docker compose -f docker/docker-compose.yml down
	docker compose -f docker/docker-compose.yml up -d