.PHONY: all clean

up:
		docker-compose up -d

upb:
		docker-compose up -d --build

down:
		docker-compose down --remove-orphans

app:
		docker-compose exec app bash

db:
		docker-compose exec db sh