# Use a imagem oficial do PHP com Apache, que é o mais parecido com XAMPP
FROM php:8.2-apache

# Copia o conteúdo da sua pasta 'escala_bombeiros' para o diretório web do servidor
COPY escala_bombeiros/ /var/www/html/
